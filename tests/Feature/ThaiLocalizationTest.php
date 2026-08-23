<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TournamentStatus;
use App\Enums\UserRole;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThaiLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_use_thai_by_default(): void
    {
        $live = Tournament::factory()->create(['status' => TournamentStatus::LIVE]);

        $this->get(route('tournaments.index'))
            ->assertOk()->assertSee('<html lang="th">', false)->assertSee('ระบบไม่แสดงรายการแข่งขันต่อสาธารณะ');
        $this->get(route('login'))->assertOk()->assertSee('เข้าสู่ระบบผู้ดูแล');
        $this->get(route('admin.setup'))->assertOk()->assertSee('สร้างผู้ดูแลระบบคนแรก');
        $this->get(route('api.docs'))->assertOk()->assertSee('คู่มือ REST API ภาษาไทย');
        $this->get($live->publicShareUrl())->assertOk()->assertSee('อัปเดตผลสด')->assertSee('สายการแข่งขันสด · ปัดด้านข้างเพื่อดูรอบถัดไป');
        $this->get(route('public.tournaments.bracket', ['tournament' => $live->public_token]))
            ->assertOk()->assertSee('สายการแข่งขันสด · ปัดด้านข้างเพื่อดูรอบถัดไป');
        $this->get(route('public.tournaments.matches', ['tournament' => $live->public_token]))
            ->assertOk()->assertSee('สถานะแมตช์และคะแนนล่าสุด');
        $this->get(route('public.tournaments.results', ['tournament' => $live->public_token]))
            ->assertOk()->assertSee('ตารางอันดับ');
    }

    public function test_administrator_pages_are_localized_in_thai(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $draft = Tournament::factory()->create(['status' => TournamentStatus::DRAFT]);
        $this->actingAs($admin);

        $this->get(route('tournaments.create'))->assertOk()->assertSee('ตั้งค่าการแข่งขันแล้วเพิ่มผู้เข้าแข่งขัน');
        $this->get(route('tournaments.settings', $draft))->assertOk()->assertSee('ข้อมูลการแข่งขัน');
        $this->get(route('tournaments.show', $draft))->assertOk()->assertSee('แชร์การแข่งขันสด');
        $this->get(route('admin.users.index'))->assertOk()->assertSee('จัดการผู้ใช้งาน');
        $this->get(route('admin.api-token.show'))->assertOk()->assertSee('API Token สำหรับผู้ดูแล');
    }

    public function test_api_uses_thai_from_accept_language_or_query_parameter(): void
    {
        $this->withHeader('Accept-Language', 'th-TH')->postJson('/api/tournaments', [])
            ->assertUnauthorized()->assertJsonPath('error.message', 'ต้องใช้ Bearer Token ที่ถูกต้อง');

        $token = str_repeat('l', 64);
        User::factory()->create(['role' => UserRole::ADMIN, 'api_token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/tournaments/not-found?lang=th')
            ->assertNotFound()->assertJsonPath('error.message', 'ไม่พบข้อมูลที่ต้องการ');
    }

    public function test_language_selector_can_switch_to_english(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post(route('locale.update', 'en'))->assertRedirect();
        $this->get(route('login'))
            ->assertOk()->assertSee('<html lang="en">', false)->assertSee('Admin login');
    }
}
