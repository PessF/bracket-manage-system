<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ParticipantStatus;
use App\Enums\TournamentStatus;
use App\Models\Participant;
use App\Models\Tournament;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ParticipantCsvImportService
{
    private const MAX_ROWS = 1000;

    /** @return array{imported: int, skipped: int, errors: list<string>} */
    public function import(Tournament $tournament, UploadedFile $file): array
    {
        if (! in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::READY], true)) {
            throw new DomainException(__('ui.roster_locked'));
        }

        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException(__('ui.csv_unreadable'));
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                throw new DomainException(__('ui.csv_empty'));
            }

            $delimiter = $this->detectDelimiter($firstLine);
            $rawHeaders = str_getcsv($firstLine, $delimiter, '"', '');
            $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), $rawHeaders);

            if (! in_array('team_name', $headers, true)) {
                throw new DomainException(__('ui.csv_missing_team_header'));
            }

            $rows = [];
            $lineNumber = 1;

            while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $lineNumber++;

                if ($this->emptyRow($values)) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw new DomainException(__('ui.csv_limit', ['count' => self::MAX_ROWS]));
                }

                $values = array_pad($values, count($headers), '');
                $data = [];

                foreach ($headers as $index => $header) {
                    if ($header !== '' && ! array_key_exists($header, $data)) {
                        $data[$header] = $values[$index] ?? '';
                    }
                }

                $rows[] = ['line' => $lineNumber, 'data' => $data];
            }
        } finally {
            fclose($handle);
        }

        return DB::transaction(fn (): array => $this->persist($tournament, $rows), 3);
    }

    /**
     * @param  list<array{line: int, data: array<string, string>}>  $rows
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    private function persist(Tournament $tournament, array $rows): array
    {
        $existingNames = $tournament->participants()->pluck('team_name')->mapWithKeys(
            fn (string $name): array => [mb_strtolower(trim($name)) => true],
        )->all();
        $seen = [];
        $errors = [];
        $imported = 0;
        $nextSeed = ((int) $tournament->participants()->max('seed_number')) + 1;

        foreach ($rows as $row) {
            $data = $row['data'];
            $teamName = trim((string) ($data['team_name'] ?? ''));
            $key = mb_strtolower($teamName);
            $seedRaw = trim((string) ($data['seed_number'] ?? ''));

            if ($teamName === '') {
                $errors[] = __('ui.csv_row_missing_name', ['row' => $row['line']]);

                continue;
            }

            if (mb_strlen($teamName) > 200) {
                $errors[] = __('ui.csv_row_name_long', ['row' => $row['line']]);

                continue;
            }

            if (isset($existingNames[$key]) || isset($seen[$key])) {
                $errors[] = __('ui.csv_row_duplicate', ['row' => $row['line'], 'name' => $teamName]);

                continue;
            }

            if ($seedRaw !== '' && (! ctype_digit($seedRaw) || (int) $seedRaw < 1)) {
                $errors[] = __('ui.csv_row_seed', ['row' => $row['line']]);

                continue;
            }

            /** @var Participant $participant */
            $participant = $tournament->participants()->create([
                'team_name' => $teamName,
                'team_code' => $this->limited($data['team_code'] ?? null, 100),
                'school' => $this->limited($data['school'] ?? null, 200),
                'coach_name' => $this->limited($data['coach_name'] ?? null, 200),
                'seed_number' => $seedRaw !== '' ? (int) $seedRaw : $nextSeed++,
                'status' => ParticipantStatus::ACTIVE,
                'source_created_at' => now(),
                'synced_at' => now(),
            ]);

            foreach ($this->memberNames($data) as $member) {
                $participant->members()->create(['name' => mb_substr($member, 0, 200)]);
            }

            $seen[$key] = true;
            $imported++;
        }

        $tournament->update([
            'participant_count' => $tournament->participants()->count(),
            'source_updated_at' => now(),
            'synced_at' => now(),
        ]);

        return ['imported' => $imported, 'skipped' => count($errors), 'errors' => $errors];
    }

    private function normalizeHeader(string $header): string
    {
        $key = mb_strtolower(trim(ltrim($header, "\xEF\xBB\xBF")));
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return [
            'team name' => 'team_name', 'team' => 'team_name', 'team_name' => 'team_name', 'teamname' => 'team_name',
            'ชื่อทีม' => 'team_name', 'ทีม' => 'team_name',
            'team id' => 'team_code', 'teamid' => 'team_code', 'team code' => 'team_code', 'team_code' => 'team_code', 'teamcode' => 'team_code',
            'รหัสทีม' => 'team_code', 'school' => 'school', 'school / organization' => 'school',
            'organization' => 'school', 'โรงเรียน' => 'school', 'สถาบัน' => 'school', 'โรงเรียน / สถาบัน' => 'school',
            'coach' => 'coach_name', 'coach name' => 'coach_name', 'coach_name' => 'coach_name', 'โค้ช' => 'coach_name', 'ชื่อโค้ช' => 'coach_name',
            'seed' => 'seed_number', 'seed number' => 'seed_number', 'seed_number' => 'seed_number', 'อันดับ seed' => 'seed_number',
            'member 1' => 'member_1', 'member1' => 'member_1', 'สมาชิก 1' => 'member_1',
            'member 2' => 'member_2', 'member2' => 'member_2', 'สมาชิก 2' => 'member_2',
            'member 3' => 'member_3', 'member3' => 'member_3', 'สมาชิก 3' => 'member_3',
            'member 4' => 'member_4', 'member4' => 'member_4', 'สมาชิก 4' => 'member_4',
            'member names' => 'member_names', 'member_names' => 'member_names', 'membernames' => 'member_names',
        ][$key] ?? str_replace(' ', '_', $key);
    }

    /**
     * @param  array<string, string>  $data
     * @return list<string>
     */
    private function memberNames(array $data): array
    {
        $members = [];

        foreach (['member_1', 'member_2', 'member_3', 'member_4'] as $memberColumn) {
            $member = trim((string) ($data[$memberColumn] ?? ''));

            if ($member !== '') {
                $members[] = $member;
            }
        }

        if ($members !== []) {
            return $members;
        }

        $combinedMembers = trim((string) ($data['member_names'] ?? ''));

        if ($combinedMembers === '') {
            return [];
        }

        $splitMembers = preg_split('/\s*(?:,|;|\R)\s*/u', $combinedMembers) ?: [];

        return array_values(array_slice(array_filter(
            $splitMembers,
            fn (string $member): bool => $member !== '',
        ), 0, 4));
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), "\t" => substr_count($line, "\t"), ';' => substr_count($line, ';')];

        return array_search(max($counts), $counts, true) ?: ',';
    }

    /** @param list<string|null> $values */
    private function emptyRow(array $values): bool
    {
        return count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0;
    }

    private function limited(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
