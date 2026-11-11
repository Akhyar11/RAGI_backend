<?php

namespace Tests\Feature\SPMB;

use App\Jobs\PublishSpmbResultNotification;
use App\Models\CoreNotification;
use App\Models\Module;
use App\Models\Role;
use App\Models\Siakad\ProgramStudi;
use App\Models\Sikeu\TagihanMahasiswa;
use App\Models\Spmb\GelombangPenerimaan;
use App\Models\Spmb\HasilSeleksi;
use App\Models\Spmb\JalurMasuk;
use App\Models\Spmb\PendaftaranCalonMhs;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\SystemInboxEmailNotification;
use App\Services\IAM\SpmbAdminRecipientService;
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
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpmbAdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    private User $adminA;

    private User $adminB;

    private PendaftaranCalonMhs $application;

    private ProgramStudi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->applicant = User::factory()->create();
        $this->adminA = User::factory()->create();
        $this->adminB = User::factory()->create();
        Module::create(['code' => SpmbNotificationService::MODULE_CODE, 'name' => 'Penerimaan', 'is_active' => true]);
        $jalur = JalurMasuk::create(['kode' => 'ADMIN-NOTIF', 'nama' => 'Jalur Notifikasi']);
        $wave = GelombangPenerimaan::create(['jalur_masuk_id' => $jalur->id, 'nama' => 'Gelombang Notifikasi', 'tanggal_buka' => '01-01', 'tanggal_tutup' => '12-31', 'status' => 'aktif']);
        $this->prodi = ProgramStudi::create(['kode_prodi' => 'ADMIN-NOTIF', 'nama' => 'Program Notifikasi', 'jenjang' => 'S1']);
        $this->application = PendaftaranCalonMhs::create([
            'user_id' => $this->applicant->id, 'gelombang_id' => $wave->id, 'program_studi_id' => $this->prodi->id,
            'no_pendaftaran' => fake()->unique()->numerify('ADMIN-NOTIF-#####'), 'nama_lengkap' => fake()->name(),
            'status' => PendaftaranCalonMhs::STATUS_DRAFT, 'status_pembayaran' => 'belum_bayar',
        ]);
    }

    public function test_mapping_is_the_only_source_of_admin_recipients(): void
    {
        $unmappedAdmin = User::factory()->create();
        $role = Role::create(['slug' => 'admin_spmb', 'name' => 'Admin SPMB', 'is_active' => true]);
        $unmappedAdmin->roles()->attach($role->id);
        $this->map([$this->adminA->id, $this->adminB->id]);
        $this->submit();
        $this->assertDatabaseHas('core_notifications', [
            'user_id' => $this->adminA->id, 'type' => 'admin_application_submitted',
            'url' => '/spmb/pendaftaran/'.$this->application->id,
        ]);
        $message = CoreNotification::where('user_id', $this->adminA->id)->firstOrFail();
        $this->assertStringContainsString($this->application->nama_lengkap, $message->message);
        $this->assertStringContainsString($this->application->no_pendaftaran, $message->message);
        $this->assertStringContainsString('menunggu verifikasi', $message->message);
        $this->assertDatabaseCount('core_notifications', 3);
        Notification::assertSentToTimes($this->adminA, SystemInboxEmailNotification::class, 1);
        Notification::assertSentToTimes($this->adminB, SystemInboxEmailNotification::class, 1);
        Notification::assertSentToTimes($this->applicant, SystemInboxEmailNotification::class, 1);
        Notification::assertNotSentTo($unmappedAdmin, SystemInboxEmailNotification::class);
        $this->assertDatabaseMissing('core_notifications', ['user_id' => $unmappedAdmin->id]);
    }

    public function test_empty_or_corrupt_mapping_keeps_applicant_notifications_working(): void
    {
        SystemSetting::set(SpmbAdminRecipientService::SETTING_KEY, '{broken');
        $this->submit();
        $this->assertDatabaseCount('core_notifications', 1);
        Notification::assertSentToTimes($this->applicant, SystemInboxEmailNotification::class, 1);
        Notification::assertNotSentTo($this->adminA, SystemInboxEmailNotification::class);
    }

    public function test_all_workflow_notifications_reach_mapped_admins_once(): void
    {
        $this->map([$this->adminA->id, $this->adminB->id]);
        $this->submit();
        app(SpmbPendaftaranService::class)->verifikasiAdministrasi($this->application->fresh(), true, null, $this->adminA->id);
        $result = $this->publish();
        $issued = $this->issueBill();
        $issued['tagihan']->update(['status' => 'lunas', 'total_bayar' => 100000]);
        $result->update(['status_daftar_ulang' => HasilSeleksi::STATUS_DAFTAR_ULANG_MENUNGGU_PEMBAYARAN]);
        $result->update(['status_daftar_ulang' => HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS]);
        $this->publish();
        $issued['tagihan']->save();
        $result->save();
        $types = ['admin_application_submitted', 'admin_application_lulus_administrasi', 'admin_result_published',
            'admin_billing_va_issued', 'admin_payment_paid', 'admin_reenrollment_menunggu_pembayaran', 'admin_reenrollment_lunas'];
        foreach ([$this->adminA, $this->adminB] as $admin) {
            $this->assertEqualsCanonicalizing($types, CoreNotification::where('user_id', $admin->id)->pluck('type')->all());
            Notification::assertSentToTimes($admin, SystemInboxEmailNotification::class, count($types));
        }
        $this->assertDatabaseCount('core_notifications', count($types) * 3);
        $this->assertSame(HasilSeleksi::STATUS_DAFTAR_ULANG_LUNAS, $result->fresh()->status_daftar_ulang);
    }

    public function test_partial_and_duplicate_payment_callbacks_do_not_duplicate_admin_messages(): void
    {
        $this->map([$this->adminA->id]);
        $this->issueBill();
        $url = '/api/v1/sikeu/callback/spmb/'.$this->application->id;
        $this->postJson($url, ['order_id' => 'ADMIN-PARTIAL', 'nominal' => 40000, 'status' => 'settlement'])->assertOk();
        $this->assertDatabaseMissing('core_notifications', ['type' => 'admin_payment_paid']);
        $payment = ['order_id' => 'ADMIN-PAID', 'nominal' => 60000, 'status' => 'settlement'];
        $this->postJson($url, $payment)->assertOk();
        $this->postJson($url, $payment)->assertOk();
        $this->assertSame(1, CoreNotification::where('user_id', $this->adminA->id)->where('type', 'admin_payment_paid')->count());
        $this->assertSame(1, CoreNotification::where('user_id', $this->adminA->id)->where('type', 'admin_application_submitted')->count());
    }

    public function test_mapping_changes_apply_to_the_next_event(): void
    {
        $this->map([$this->adminA->id]);
        $this->submit();
        $this->map([$this->adminB->id]);
        app(SpmbPendaftaranService::class)->verifikasiAdministrasi($this->application->fresh(), false, 'Lengkapi dokumen', $this->adminA->id);
        $this->assertDatabaseMissing('core_notifications', ['user_id' => $this->adminA->id, 'type' => 'admin_application_gagal_administrasi']);
        $this->assertDatabaseHas('core_notifications', ['user_id' => $this->adminB->id, 'type' => 'admin_application_gagal_administrasi']);
        Notification::assertSentToTimes($this->adminA, SystemInboxEmailNotification::class, 1);
        Notification::assertSentToTimes($this->adminB, SystemInboxEmailNotification::class, 1);
    }

    public function test_disabled_deleted_and_duplicate_recipients_are_handled_at_dispatch(): void
    {
        $deleted = User::factory()->create();
        $this->map([$this->adminA->id, $this->adminB->id, $deleted->id]);
        $this->adminB->update(['is_active' => false]);
        $deleted->delete();
        SystemSetting::set(SpmbAdminRecipientService::SETTING_KEY, json_encode([$this->adminA->id, $this->adminA->id, $this->adminB->id, $deleted->id]));
        $this->submit();
        $this->assertDatabaseCount('core_notifications', 2);
        Notification::assertSentToTimes($this->adminA, SystemInboxEmailNotification::class, 1);
        Notification::assertNotSentTo($this->adminB, SystemInboxEmailNotification::class);
    }

    public function test_applicant_is_not_given_a_second_copy_as_admin_of_the_same_application(): void
    {
        $this->map([$this->applicant->id, $this->adminA->id]);
        $this->submit();
        Notification::assertSentToTimes($this->applicant, SystemInboxEmailNotification::class, 1);
        $this->assertDatabaseMissing('core_notifications', ['user_id' => $this->applicant->id, 'type' => 'admin_application_submitted']);
        $this->assertDatabaseCount('core_notifications', 2);
    }

    public function test_rollback_does_not_leave_admin_inbox_or_email(): void
    {
        $this->map([$this->adminA->id]);
        try {
            DB::transaction(function (): void {
                $this->submit();
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('core_notifications', 0);
            Notification::assertNothingSent();
        }
    }

    public function test_admin_email_job_serializes_and_delivers_the_correct_message(): void
    {
        $this->map([$this->adminA->id]);
        $manager = new ChannelManager($this->app);
        Notification::swap($manager);
        Queue::fake([SendQueuedNotifications::class]);
        $this->submit();
        $queued = null;
        Queue::assertPushedOn('notifications', SendQueuedNotifications::class, function ($job) use (&$queued): bool {
            if ($job->notifiables->first()->id !== $this->adminA->id) {
                return false;
            }
            $queued = $job;

            return $job->afterCommit === true && $job->channels === ['mail'];
        });
        unserialize(serialize($queued))->handle($manager);
        $email = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame($this->adminA->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Admin SPMB:', $email->getSubject());
        $this->assertStringContainsString($this->application->nama_lengkap, $email->getHtmlBody());
        $this->assertStringContainsString('/spmb/pendaftaran/'.$this->application->id, $email->getHtmlBody());
    }

    public function test_admin_read_status_is_private_and_independent_from_other_recipients(): void
    {
        $this->map([$this->adminA->id, $this->adminB->id]);
        $this->submit();
        Passport::actingAs($this->adminA);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'admin_application_submitted');
        $other = CoreNotification::where('user_id', $this->adminB->id)->firstOrFail();
        $this->postJson('/api/notifications/'.$other->id.'/read')->assertNotFound();
        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 1);
        $this->assertFalse($other->fresh()->is_read);
        $this->assertFalse(CoreNotification::where('user_id', $this->applicant->id)->firstOrFail()->is_read);
    }

    public function test_scheduled_publication_uses_current_mapping_and_deduplicates_job_retries(): void
    {
        Queue::fake();
        $this->map([$this->adminA->id]);
        $result = $this->publish(['diumumkan_at' => now()->addDay()]);
        $this->assertDatabaseCount('core_notifications', 0);
        $this->map([$this->adminB->id]);
        $this->travel(2)->days();
        $job = new PublishSpmbResultNotification($result->id);
        $job->handle(app(SpmbNotificationService::class));
        $job->handle(app(SpmbNotificationService::class));
        $this->assertDatabaseCount('core_notifications', 2);
        $this->assertDatabaseMissing('core_notifications', ['user_id' => $this->adminA->id]);
        $this->assertDatabaseHas('core_notifications', ['user_id' => $this->adminB->id, 'type' => 'admin_result_published']);
    }

    public function test_manual_submitted_application_notifies_mapped_admin(): void
    {
        $this->map([$this->adminA->id]);
        $manual = $this->application->replicate();
        $manual->no_pendaftaran = fake()->unique()->numerify('MANUAL-ADMIN-#####');
        $manual->status = PendaftaranCalonMhs::STATUS_SUBMITTED;
        $manual->sumber_pendaftaran = 'manual';
        $manual->diinput_oleh = $this->adminA->id;
        $manual->save();
        $this->assertDatabaseHas('core_notifications', ['user_id' => $this->adminA->id, 'entity_id' => $manual->id, 'type' => 'admin_application_submitted']);
    }

    private function map(array $ids): void
    {
        app(SpmbAdminRecipientService::class)->save(json_encode($ids));
    }

    private function submit(): void
    {
        app(SpmbPendaftaranService::class)->submitPendaftaran($this->application->fresh());
    }

    private function publish(array $overrides = []): HasilSeleksi
    {
        return app(SpmbPendaftaranService::class)->tetapkanKelulusan($this->application, array_merge([
            'status' => HasilSeleksi::STATUS_LULUS, 'program_studi_diterima_id' => $this->prodi->id,
        ], $overrides));
    }

    private function issueBill(): array
    {
        return app(ExternalTagihanService::class)->issueExternalBill([
            'calon_mahasiswa_id' => $this->application->id, 'source_system' => SpmbNotificationService::SOURCE_SYSTEM,
            'tipe_referensi' => TagihanMahasiswa::TIPE_SPMB_PENDAFTARAN,
            'details' => [['master_biaya_kode' => 'ADMIN-NOTIF-ADM', 'nominal' => 100000]],
        ]);
    }
}
