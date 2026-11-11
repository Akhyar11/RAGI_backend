<?php

namespace Tests\Feature\SPMB;

use App\Jobs\PublishSpmbResultNotification;
use App\Models\CoreNotification;
use App\Models\Lms\LmsNotifikasi;
use App\Models\Module;
use App\Models\Siakad\ProgramStudi;
use App\Models\Siakad\TahunAkademik;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\Spmb\SpmbKuotaProdi;
use App\Models\User;
use App\Notifications\SystemInboxEmailNotification;
use App\Services\Notifications\SystemNotificationService;
use App\Services\Sikeu\ExternalTagihanService;
use App\Services\Spmb\SpmbNotificationService;
use App\Services\Spmb\SpmbPendaftaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpmbSystemNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private User $admin;

    private PendaftaranCalonMhs $application;

    private ProgramStudi $prodi;

    private TahunAkademik $year;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->applicant = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->module = Module::create(['code' => SpmbNotificationService::MODULE_CODE, 'name' => 'Penerimaan', 'is_active' => true]);
        $this->prodi = ProgramStudi::create(['kode_prodi' => 'NOTIF', 'nama' => 'Program Notifikasi', 'jenjang' => 'S1']);
        $this->year = TahunAkademik::create(['kode' => '20261', 'nama' => '2026/2027', 'tahun_mulai' => 2026, 'tahun_selesai' => 2027, 'is_active' => true]);
        $jalur = JalurMasuk::create(['kode' => 'NOTIF', 'nama' => 'Jalur Notifikasi']);
        $wave = GelombangPenerimaan::create(['jalur_masuk_id' => $jalur->id, 'nama' => 'Gelombang Notifikasi', 'tanggal_buka' => '01-01', 'tanggal_tutup' => '12-31', 'status' => 'aktif']);
        $this->application = PendaftaranCalonMhs::create([
            'gelombang_id' => $wave->id,
            'user_id' => $this->applicant->id, 'program_studi_id' => $this->prodi->id,
            'no_pendaftaran' => fake()->unique()->numerify('NOTIF-#####'), 'nama_lengkap' => $this->applicant->name,
            'status' => PendaftaranCalonMhs::STATUS_DRAFT, 'status_pembayaran' => 'belum_bayar',
        ]);
    }

    public function test_submission_and_both_verification_paths_notify_only_applicant_once(): void
    {
        Passport::actingAs($this->admin);
        $service = app(SpmbPendaftaranService::class);
        $service->submitPendaftaran($this->application);
        $service->verifikasiAdministrasi($this->application->fresh(), true, null, $this->admin->id);
        $service->updateStatusWithReferral($this->application->fresh(), ['status' => PendaftaranCalonMhs::STATUS_GAGAL_ADMINISTRASI, 'catatan_verifikasi' => 'Lengkapi ijazah'], $this->admin->id);
        $service->updateStatusWithReferral($this->application->fresh(), ['status' => PendaftaranCalonMhs::STATUS_GAGAL_ADMINISTRASI], $this->admin->id);
        $this->assertSame(3, CoreNotification::count());
        $this->assertSame([$this->applicant->id], CoreNotification::pluck('user_id')->unique()->all());
        $this->assertDatabaseHas('core_notifications', ['type' => 'application_gagal_administrasi', 'module_id' => $this->module->id]);
        Notification::assertSentToTimes($this->applicant, SystemInboxEmailNotification::class, 3);
        Notification::assertNotSentTo($this->admin, SystemInboxEmailNotification::class);
    }

    public function test_manual_submitted_record_also_triggers_notification(): void
    {
        $manual = $this->application->replicate();
        $manual->no_pendaftaran = fake()->unique()->numerify('MANUAL-#####');
        $manual->sumber_pendaftaran = 'manual';
        $manual->status = PendaftaranCalonMhs::STATUS_SUBMITTED;
        $manual->save();
        $this->assertDatabaseHas('core_notifications', ['user_id' => $this->applicant->id, 'entity_id' => $manual->id, 'type' => 'application_submitted']);
    }

    public function test_draft_and_unrelated_updates_do_not_notify(): void
    {
        $this->application->update(['nama_lengkap' => fake()->name()]);
        $this->assertDatabaseCount('core_notifications', 0);
        Notification::assertNothingSent();
    }

    public function test_result_resave_does_not_increment_quota_or_reset_reenrollment(): void
    {
        $quota = $this->quota($this->prodi, 1);
        $result = $this->publish();
        $publishedAt = $result->diumumkan_at->toISOString();
        $result->update(['status_daftar_ulang' => HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS]);
        $this->travel(1)->hours();
        $resaved = $this->publish();
        $this->assertSame(1, $quota->fresh()->kuota_terisi);
        $this->assertSame($publishedAt, $resaved->diumumkan_at->toISOString());
        $this->assertSame(HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS, $resaved->status_daftar_ulang);
        $this->assertSame(1, CoreNotification::where('type', 'result_published')->count());
        $this->assertDatabaseHas('spmb_hasil_seleksi', ['id' => $result->id, 'status_daftar_ulang' => 'lunas']);
    }

    public function test_quota_transfers_and_releases_when_decision_changes(): void
    {
        $origin = $this->quota($this->prodi, 2);
        $targetProdi = ProgramStudi::create(['kode_prodi' => 'TARGET', 'nama' => 'Program Tujuan', 'jenjang' => 'S1']);
        $target = $this->quota($targetProdi, 1);
        $this->publish();
        $this->publish(['program_studi_diterima_id' => $targetProdi->id]);
        $this->assertSame(0, $origin->fresh()->kuota_terisi);
        $this->assertSame(1, $target->fresh()->kuota_terisi);
        $this->publish(['status' => HasilSeleksi::STATUS_TIDAK_LULUS, 'program_studi_diterima_id' => null]);
        $this->assertSame(0, $target->fresh()->kuota_terisi);
        $this->assertDatabaseHas('spmb_hasil_seleksi', ['pendaftaran_id' => $this->application->id, 'status' => HasilSeleksi::STATUS_TIDAK_LULUS]);
    }

    public function test_full_target_rolls_back_quota_result_and_notification(): void
    {
        $origin = $this->quota($this->prodi, 1);
        $targetProdi = ProgramStudi::create(['kode_prodi' => 'FULL', 'nama' => 'Program Penuh', 'jenjang' => 'S1']);
        $this->quota($targetProdi, 0);
        $this->publish();
        try {
            $this->publish(['program_studi_diterima_id' => $targetProdi->id]);
            $this->fail('Kuota penuh harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame(1, $origin->fresh()->kuota_terisi);
            $this->assertDatabaseHas('spmb_hasil_seleksi', ['pendaftaran_id' => $this->application->id, 'program_studi_diterima_id' => $this->prodi->id]);
            $this->assertSame(1, CoreNotification::where('type', 'result_published')->count());
        }
    }

    public function test_unpublished_and_future_results_wait_for_publication_and_job_retries_deduplicate(): void
    {
        Queue::fake();
        $result = $this->publish(['diumumkan_at' => null]);
        $this->assertDatabaseCount('core_notifications', 0);
        $result->update(['diumumkan_at' => now()->addDay()]);
        Queue::assertPushed(PublishSpmbResultNotification::class, fn ($job) => $job->resultId === $result->id && $job->delay->isFuture());
        $this->assertDatabaseCount('core_notifications', 0);
        $job = new PublishSpmbResultNotification($result->id);
        $job->handle(app(SpmbNotificationService::class));
        $this->assertDatabaseCount('core_notifications', 0);
        $this->travel(2)->days();
        $job->handle(app(SpmbNotificationService::class));
        $job->handle(app(SpmbNotificationService::class));
        $this->assertDatabaseCount('core_notifications', 1);
    }

    public function test_va_partial_and_full_payment_callback_notifications_are_idempotent(): void
    {
        $issued = $this->issueBill();
        $account = $issued['virtual_account'];
        $account->save();
        $this->assertSame(1, CoreNotification::where('type', 'billing_va_issued')->count());
        $endpoint = '/api/v1/sikeu/callback/spmb/'.$this->application->id;
        $this->postJson($endpoint, ['order_id' => 'PARTIAL-NOTIF', 'nominal' => 40000, 'status' => 'settlement'])->assertOk();
        $this->assertSame(0, CoreNotification::where('type', 'payment_paid')->count());
        $payload = ['order_id' => 'FULL-NOTIF', 'nominal' => 60000, 'status' => 'settlement'];
        $this->postJson($endpoint, $payload)->assertOk()->assertJsonPath('is_spmb_unlocked', true);
        $this->postJson($endpoint, $payload)->assertOk();
        $this->assertSame(1, CoreNotification::where('type', 'payment_paid')->count());
        $this->assertSame(1, CoreNotification::where('type', 'application_submitted')->count());
        $this->assertDatabaseHas('sikeu_tagihan_mahasiswa', ['id' => $issued['tagihan']->id, 'status' => 'lunas']);
        $this->assertSame([$this->applicant->id], CoreNotification::pluck('user_id')->unique()->all());
    }

    public function test_pending_approval_bill_does_not_send_va_notification(): void
    {
        $this->issueBill(['requires_approval' => true]);
        $this->assertDatabaseCount('core_notifications', 0);
    }

    public function test_reenrollment_waiting_paid_and_resave_notify_once_per_state(): void
    {
        $result = $this->publish();
        $result->update(['status_daftar_ulang' => 'menunggu_pembayaran']);
        $result->update(['status_daftar_ulang' => 'lunas']);
        $result->save();
        $this->assertSame(1, CoreNotification::where('type', 'reenrollment_menunggu_pembayaran')->count());
        $this->assertSame(1, CoreNotification::where('type', 'reenrollment_lunas')->count());
        $this->assertDatabaseHas('core_notifications', ['user_id' => $this->applicant->id, 'type' => 'reenrollment_lunas']);
    }

    public function test_email_waits_until_commit_and_deduplicates(): void
    {
        DB::transaction(function (): void {
            $this->createInbox('email');
            Notification::assertNothingSent();
        });
        $this->assertNull(app(SystemNotificationService::class)->create($this->applicant, ['type' => 'info', 'title' => 'Inbox email', 'dedup_key' => 'email']));
        Notification::assertSentToTimes($this->applicant, SystemInboxEmailNotification::class, 1);
        $this->assertDatabaseCount('core_notifications', 1);
        $mail = Notification::sent($this->applicant, SystemInboxEmailNotification::class)->first();
        $this->assertSame(['mail'], $mail->via($this->applicant));
        $this->assertSame('notifications', $mail->queue);
        $this->assertSame(rtrim(config('app.frontend_url', config('app.url')), '/').'/spmb/dashboard', $mail->toMail($this->applicant)->actionUrl);
    }

    public function test_rollback_removes_inbox_and_never_sends_email(): void
    {
        try {
            DB::transaction(function (): void {
                $this->application->update(['status' => PendaftaranCalonMhs::STATUS_SUBMITTED]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('core_notifications', 0);
            Notification::assertNothingSent();
            $this->assertSame(PendaftaranCalonMhs::STATUS_DRAFT, $this->application->fresh()->status);
        }
    }

    public function test_queued_email_serializes_and_delivers_to_the_applicant(): void
    {
        $manager = new ChannelManager($this->app);
        Notification::swap($manager);
        Queue::fake([SendQueuedNotifications::class]);
        DB::transaction(fn () => $this->createInbox('queued-mail'));
        $queued = null;
        Queue::assertPushedOn('notifications', SendQueuedNotifications::class, function ($job) use (&$queued): bool {
            $queued = $job;

            return $job->notification instanceof SystemInboxEmailNotification
                && $job->channels === ['mail'] && $job->afterCommit === true;
        });
        $restored = unserialize(serialize($queued));
        $restored->handle($manager);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $email = $messages->first()->getOriginalMessage();
        $this->assertSame($this->applicant->email, $email->getTo()[0]->getAddress());
        $this->assertSame('Inbox queued-mail', $email->getSubject());
        $this->assertStringContainsString('Pembaruan pendaftaran', $email->getHtmlBody());
        $this->assertDatabaseHas('core_notifications', ['user_id' => $this->applicant->id, 'title' => 'Inbox queued-mail']);
    }

    public function test_email_transport_failure_does_not_fail_business_write(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('transport unavailable'));
        DB::transaction(fn () => $this->application->update(['status' => PendaftaranCalonMhs::STATUS_SUBMITTED]));
        $this->assertDatabaseHas('core_notifications', ['user_id' => $this->applicant->id, 'type' => 'application_submitted']);
        $this->assertSame(PendaftaranCalonMhs::STATUS_SUBMITTED, $this->application->fresh()->status);
    }

    public function test_no_email_address_still_gets_inbox(): void
    {
        $this->applicant->email = '';
        $this->createInbox('no-mail');
        Notification::assertNothingSent();
        $this->assertDatabaseCount('core_notifications', 1);
    }

    public function test_inbox_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/notifications/1/read')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
    }

    public function test_inbox_scopes_filters_pagination_and_validates_input(): void
    {
        $this->createInbox('one');
        $this->createInbox('two');
        app(SystemNotificationService::class)->create($this->admin, ['title' => 'Other', 'type' => 'info', 'dedup_key' => 'one']);
        Passport::actingAs($this->applicant);
        $this->getJson('/api/notifications?per_page=1&sort_order=asc&search=Inbox&module_id='.$this->module->id)
            ->assertOk()->assertJsonStructure(['status', 'data' => [['id', 'title', 'is_read', 'module']], 'meta' => ['current_page', 'total', 'per_page'], 'filters'])
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/notifications?per_page=200')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/notifications?sort_by=invalid')->assertUnprocessable();
        $this->getJson('/api/notifications?is_read=invalid')->assertUnprocessable();
        $this->getJson('/api/notifications?is_read=false')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/notifications?is_read=true')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/notifications?is_read=')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 2);
    }

    public function test_read_is_idempotent_private_and_does_not_change_lms(): void
    {
        $own = $this->createInbox('own');
        $other = app(SystemNotificationService::class)->create($this->admin, ['type' => 'info', 'title' => 'Other', 'dedup_key' => 'own']);
        $lms = LmsNotifikasi::create(['user_id' => $this->applicant->id, 'jenis' => 'info', 'judul' => 'LMS retained', 'prioritas' => 'normal', 'is_read' => false, 'dedup_key' => 'lms']);
        Passport::actingAs($this->applicant);
        $readAt = $this->postJson('/api/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('data.is_read', true)->json('data.read_at');
        $this->travel(1)->hours();
        $this->postJson('/api/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('data.read_at', $readAt);
        $this->postJson('/api/notifications/'.$other->id.'/read')->assertNotFound()->assertJsonPath('status', 'error');
        $this->createInbox('next');
        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 1);
        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 0);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->getJson('/api/v1/lms/notifikasi')->assertOk()->assertJsonPath('data.0.judul', 'LMS retained');
        $this->assertDatabaseHas('core_notifications', ['id' => $own->id, 'is_read' => true]);
        $this->assertFalse($other->fresh()->is_read);
        $this->assertFalse($lms->fresh()->is_read);
    }

    private function quota(ProgramStudi $prodi, int $total): SpmbKuotaProdi
    {
        return SpmbKuotaProdi::create(['tahun_akademik_id' => $this->year->id, 'program_studi_id' => $prodi->id, 'kuota_total' => $total, 'kuota_terisi' => 0]);
    }

    private function publish(array $overrides = []): HasilSeleksi
    {
        return app(SpmbPendaftaranService::class)->tetapkanKelulusan($this->application, array_merge([
            'status' => HasilSeleksi::STATUS_LULUS, 'program_studi_diterima_id' => $this->prodi->id,
        ], $overrides));
    }

    private function issueBill(array $overrides = []): array
    {
        return app(ExternalTagihanService::class)->issueExternalBill(array_merge([
            'calon_mahasiswa_id' => $this->application->id, 'source_system' => SpmbNotificationService::SOURCE_SYSTEM,
            'tipe_referensi' => TagihanMahasiswa::TIPE_SPMB_PENDAFTARAN,
            'details' => [['master_biaya_kode' => 'NOTIF-ADM', 'nominal' => 100000]],
        ], $overrides));
    }

    private function createInbox(string $key): CoreNotification
    {
        return app(SystemNotificationService::class)->create($this->applicant, [
            'module_id' => $this->module->id, 'type' => 'info', 'title' => 'Inbox '.$key,
            'message' => 'Pembaruan pendaftaran', 'url' => SpmbNotificationService::APPLICATION_URL, 'dedup_key' => $key,
        ]);
    }
}
