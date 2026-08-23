<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BracketType;
use App\Enums\MatchSlot;
use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\SeedingMethod;
use App\Enums\StageStatus;
use App\Enums\TournamentFormat;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Stage;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchResultService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class MatchResultServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_atomically_propagates_winner_and_loser_and_readies_destinations(): void
    {
        [$tournament, $stage] = $this->createTournament(TournamentFormat::DOUBLE_ELIMINATION);
        [$participantA, $participantB, $participantC, $participantD] = $this->createParticipants(
            $tournament,
            4,
        );

        $winnerDestination = $this->createMatch($tournament, $stage, [
            'match_number' => 2,
            'participant_a_id' => $participantC->id,
            'participant_b_label' => 'Winner of Match #1',
        ]);
        $loserDestination = $this->createMatch($tournament, $stage, [
            'match_number' => 3,
            'bracket_type' => BracketType::LOSERS,
            'participant_a_label' => 'Loser of Match #1',
            'participant_b_id' => $participantD->id,
        ]);
        $source = $this->createMatch($tournament, $stage, [
            'match_number' => 1,
            'status' => MatchStatus::READY,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'winner_next_match_id' => $winnerDestination->id,
            'winner_next_slot' => MatchSlot::B,
            'loser_next_match_id' => $loserDestination->id,
            'loser_next_slot' => MatchSlot::A,
        ]);

        $result = app(MatchResultService::class)->confirm($source, '10.5', '3');

        $this->assertSame(MatchStatus::FINISHED, $result->status);
        $this->assertSame('10.500000', $result->score_a);
        $this->assertSame('3.000000', $result->score_b);
        $this->assertSame($participantA->id, $result->winner_id);
        $this->assertSame($participantB->id, $result->loser_id);
        $this->assertNotNull($result->started_at);
        $this->assertNotNull($result->finished_at);

        $winnerDestination->refresh();
        $loserDestination->refresh();

        $this->assertSame($participantA->id, $winnerDestination->participant_b_id);
        $this->assertSame(MatchStatus::READY, $winnerDestination->status);
        $this->assertSame($participantB->id, $loserDestination->participant_a_id);
        $this->assertSame(MatchStatus::READY, $loserDestination->status);

        $corrected = app(MatchResultService::class)->confirm($source, '2', '11');
        $winnerDestination->refresh();
        $loserDestination->refresh();

        $this->assertSame($participantB->id, $corrected->winner_id);
        $this->assertSame($participantB->id, $winnerDestination->participant_b_id);
        $this->assertSame($participantA->id, $loserDestination->participant_a_id);
    }

    public function test_it_rejects_an_elimination_tie_without_partial_writes(): void
    {
        [$tournament, $stage] = $this->createTournament(TournamentFormat::SINGLE_ELIMINATION);
        [$participantA, $participantB] = $this->createParticipants($tournament, 2);
        $source = $this->createMatch($tournament, $stage, [
            'status' => MatchStatus::READY,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
        ]);

        try {
            app(MatchResultService::class)->confirm($source, '4', '4');
            $this->fail('An elimination tie should have been rejected.');
        } catch (DomainException $exception) {
            $this->assertSame(__('ui.elimination_tie_invalid'), $exception->getMessage());
        }

        $source->refresh();

        $this->assertSame(MatchStatus::READY, $source->status);
        $this->assertNull($source->score_a);
        $this->assertNull($source->score_b);
        $this->assertNull($source->winner_id);
        $this->assertNull($source->finished_at);
    }

    public function test_it_allows_a_round_robin_tie_without_a_winner_or_loser(): void
    {
        [$tournament, $stage] = $this->createTournament(TournamentFormat::ROUND_ROBIN);
        [$participantA, $participantB] = $this->createParticipants($tournament, 2);
        $source = $this->createMatch($tournament, $stage, [
            'bracket_type' => BracketType::ROUND_ROBIN,
            'status' => MatchStatus::READY,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
        ]);

        $result = app(MatchResultService::class)->confirm($source, '2', '2');

        $this->assertSame(MatchStatus::FINISHED, $result->status);
        $this->assertNull($result->winner_id);
        $this->assertNull($result->loser_id);
        $standings = $tournament->standings()->get()->keyBy('participant_id');
        $this->assertSame(1, $standings[$participantA->id]->played);
        $this->assertSame(1, $standings[$participantA->id]->draws);
        $this->assertSame(0, $standings[$participantA->id]->points);
        $this->assertSame(0, $standings[$participantB->id]->points);

        app(MatchResultService::class)->confirm($source, '4', '2');
        $standings = $tournament->standings()->get()->keyBy('participant_id');

        $this->assertSame(1, $standings[$participantA->id]->points);
        $this->assertSame(1, $standings[$participantA->id]->wins);
        $this->assertSame(0, $standings[$participantA->id]->draws);
        $this->assertSame(0, $standings[$participantB->id]->points);
    }

    public function test_losers_bracket_finalist_winning_first_grand_final_creates_reset(): void
    {
        [$tournament, $stage] = $this->createTournament(TournamentFormat::DOUBLE_ELIMINATION, 2);
        [$winnersFinalist, $losersFinalist] = $this->createParticipants($tournament, 2);
        $grandFinal = $this->createMatch($tournament, $stage, [
            'bracket_type' => BracketType::GRAND_FINAL,
            'round_number' => 100,
            'status' => MatchStatus::READY,
            'participant_a_id' => $winnersFinalist->id,
            'participant_a_label' => $winnersFinalist->team_name,
            'participant_b_id' => $losersFinalist->id,
            'participant_b_label' => $losersFinalist->team_name,
        ]);

        app(MatchResultService::class)->confirm($grandFinal, 1, 2);

        $reset = $tournament->matches()->where('match_number', 2)->firstOrFail();
        $this->assertSame(BracketType::GRAND_FINAL, $reset->bracket_type);
        $this->assertSame(MatchStatus::READY, $reset->status);
        $this->assertSame($winnersFinalist->id, $reset->participant_a_id);
        $this->assertSame($losersFinalist->id, $reset->participant_b_id);

        app(MatchResultService::class)->confirm($grandFinal, 3, 1);

        $this->assertSame(1, $tournament->matches()->where('bracket_type', BracketType::GRAND_FINAL)->count());
    }

    public function test_single_grand_final_mode_does_not_create_a_reset_match(): void
    {
        [$tournament, $stage] = $this->createTournament(TournamentFormat::DOUBLE_ELIMINATION, 1);
        [$winnersFinalist, $losersFinalist] = $this->createParticipants($tournament, 2);
        $grandFinal = $this->createMatch($tournament, $stage, [
            'bracket_type' => BracketType::GRAND_FINAL,
            'round_number' => 100,
            'status' => MatchStatus::READY,
            'participant_a_id' => $winnersFinalist->id,
            'participant_a_label' => $winnersFinalist->team_name,
            'participant_b_id' => $losersFinalist->id,
            'participant_b_label' => $losersFinalist->team_name,
        ]);

        app(MatchResultService::class)->confirm($grandFinal, 1, 2);

        $this->assertSame(1, $tournament->matches()->where('bracket_type', BracketType::GRAND_FINAL)->count());
        $this->assertDatabaseMissing('external_matches', [
            'tournament_id' => $tournament->id,
            'match_number' => 2,
        ]);
    }

    public function test_a_propagation_conflict_rolls_back_the_confirmed_result(): void
    {
        [$tournament, $stage] = $this->createTournament(TournamentFormat::DOUBLE_ELIMINATION);
        [$participantA, $participantB, $participantC] = $this->createParticipants($tournament, 3);
        $destination = $this->createMatch($tournament, $stage, [
            'match_number' => 2,
            'participant_a_id' => $participantC->id,
        ]);
        $source = $this->createMatch($tournament, $stage, [
            'match_number' => 1,
            'status' => MatchStatus::READY,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'winner_next_match_id' => $destination->id,
            'winner_next_slot' => MatchSlot::A,
        ]);

        try {
            app(MatchResultService::class)->confirm($source, '5', '1');
            $this->fail('A conflicting destination should have been rejected.');
        } catch (LogicException $exception) {
            $this->assertSame(
                __('ui.destination_occupied', ['outcome' => __('ui.outcome_labels.winner')]),
                $exception->getMessage(),
            );
        }

        $source->refresh();
        $destination->refresh();

        $this->assertSame(MatchStatus::READY, $source->status);
        $this->assertNull($source->score_a);
        $this->assertNull($source->winner_id);
        $this->assertSame($participantC->id, $destination->participant_a_id);
    }

    public function test_winner_correction_is_blocked_after_the_next_match_started(): void
    {
        [$tournament, $stage] = $this->createTournament(TournamentFormat::SINGLE_ELIMINATION);
        [$participantA, $participantB, $participantC] = $this->createParticipants($tournament, 3);
        $destination = $this->createMatch($tournament, $stage, [
            'match_number' => 2,
            'participant_b_id' => $participantC->id,
        ]);
        $source = $this->createMatch($tournament, $stage, [
            'match_number' => 1,
            'status' => MatchStatus::READY,
            'participant_a_id' => $participantA->id,
            'participant_b_id' => $participantB->id,
            'winner_next_match_id' => $destination->id,
            'winner_next_slot' => MatchSlot::A,
        ]);

        app(MatchResultService::class)->confirm($source, 5, 1);
        $destination->forceFill(['status' => MatchStatus::LIVE])->save();
        $sameWinnerCorrection = app(MatchResultService::class)->confirm($source, 7, 2);

        $this->assertSame('7.000000', $sameWinnerCorrection->score_a);
        $this->assertSame($participantA->id, $sameWinnerCorrection->winner_id);

        try {
            app(MatchResultService::class)->confirm($source, 1, 5);
            $this->fail('Changing a winner after the next match starts should fail.');
        } catch (DomainException $exception) {
            $this->assertSame(
                __('ui.score_correction_next_match_started', ['number' => 2]),
                $exception->getMessage(),
            );
        }

        $source->refresh();
        $this->assertSame($participantA->id, $source->winner_id);
        $this->assertSame('7.000000', $source->score_a);
        $this->assertSame('2.000000', $source->score_b);
    }

    /**
     * @return array{Tournament, Stage}
     */
    private function createTournament(TournamentFormat $format, int $grandFinalMatches = 2): array
    {
        $now = now();
        $tournament = Tournament::query()->create([
            'name' => 'Test Tournament',
            'competition' => 'EasyKids Robotics',
            'division' => 'Junior',
            'format' => $format,
            'seeding_method' => SeedingMethod::MANUAL,
            'status' => TournamentStatus::LIVE,
            'double_elimination_config' => $format === TournamentFormat::DOUBLE_ELIMINATION
                ? ['grand_final_matches' => $grandFinalMatches]
                : null,
            'source_created_at' => $now,
            'source_updated_at' => $now,
        ]);
        $stage = Stage::query()->create([
            'tournament_id' => $tournament->id,
            'name' => 'Main Stage',
            'stage_order' => 1,
            'format' => $format,
            'status' => StageStatus::LIVE,
            'source_created_at' => $now,
        ]);

        return [$tournament, $stage];
    }

    /**
     * @return list<Participant>
     */
    private function createParticipants(Tournament $tournament, int $count): array
    {
        $participants = [];

        for ($index = 1; $index <= $count; $index++) {
            $participants[] = Participant::query()->create([
                'tournament_id' => $tournament->id,
                'team_name' => "Team {$index}",
                'seed_number' => $index,
                'status' => ParticipantStatus::ACTIVE,
                'source_created_at' => now(),
            ]);
        }

        return $participants;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMatch(Tournament $tournament, Stage $stage, array $overrides): TournamentMatch
    {
        return TournamentMatch::query()->create(array_merge([
            'tournament_id' => $tournament->id,
            'stage_id' => $stage->id,
            'match_number' => 1,
            'bracket_type' => BracketType::WINNERS,
            'round_number' => 1,
            'status' => MatchStatus::PENDING,
            'is_bye' => false,
            'participant_a_label' => 'Participant A',
            'participant_b_label' => 'Participant B',
        ], $overrides));
    }
}
