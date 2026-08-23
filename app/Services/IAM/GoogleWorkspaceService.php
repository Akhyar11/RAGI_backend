<?php

namespace App\Services\IAM;

use Exception;
use Google\Client;
use Google\Service\Directory;
use Google\Service\Directory\User as GoogleUser;
use Google\Service\Directory\UserName;
use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;

class GoogleWorkspaceService
{
    protected ?Client $client;
    protected ?Directory $directory;
    protected string $domain;
    protected string $adminEmail;

    public function __construct()
    {
        // Load from SystemSettings Database first, fallback to config/env
        $dbSettings = SystemSetting::whereIn('key', [
            'google_workspace_credentials',
            'google_workspace_admin_email',
            'google_workspace_domain'
        ])->get()->keyBy('key');

        $credJsonStr = $dbSettings->get('google_workspace_credentials')?->value;
        
        $this->adminEmail = $dbSettings->get('google_workspace_admin_email')?->value 
            ?: config('services.google_workspace.admin_email', '');
            
        $this->domain = $dbSettings->get('google_workspace_domain')?->value 
            ?: config('services.google_workspace.domain', 'student.campus.ac.id');

        // Parse Credentials JSON
        $credentialsArray = null;
        if (!empty($credJsonStr)) {
            $credentialsArray = json_decode($credJsonStr, true);
        } else {
            // Fallback to File path in ENV
            $credentialsPath = config('services.google_workspace.credentials_json');
            if ($credentialsPath && file_exists($credentialsPath)) {
                $credentialsArray = json_decode(file_get_contents($credentialsPath), true);
            }
        }

        if (empty($credentialsArray) || !$this->adminEmail) {
            Log::warning("Google Workspace credentials or admin email not properly configured in Settings/ENV.");
            $this->client = null;
            $this->directory = null;
            return;
        }

        try {
            $this->client = new Client();
            $this->client->setAuthConfig($credentialsArray);
            
            // Must specify scopes
            $this->client->setScopes([
                Directory::ADMIN_DIRECTORY_USER,
                Directory::ADMIN_DIRECTORY_USER_SECURITY,
            ]);
            // Impersonate the admin user (Domain-Wide Delegation)
            $this->client->setSubject($this->adminEmail);

            $this->directory = new Directory($this->client);
        } catch (Exception $e) {
            Log::error("Failed to initialize Google Workspace Client: " . $e->getMessage());
            $this->client = null;
            $this->directory = null;
        }
    }

    /**
     * Membuat user di Google Workspace.
     * Mengembalikan alamat email yang berhasil dibuat atau null jika gagal.
     */
    public function createUser(string $firstName, string $lastName, string $emailPrefix, ?string $password = null): ?string
    {
        if (!$this->directory) {
            Log::error("Google Workspace Directory service is not initialized. Cannot create user.");
            // Return dummy email in development/local if intended, or just null to fallback
            return null;
        }

        $email = strtolower($emailPrefix) . '@' . $this->domain;
        $defaultPassword = $password ?? config('services.google_workspace.default_password');
        $orgUnitPath = config('services.google_workspace.org_unit_path');

        $user = new GoogleUser();
        $user->setPrimaryEmail($email);
        $user->setPassword($defaultPassword);
        $user->setOrgUnitPath($orgUnitPath);

        // Optional: Force user to change password on first login
        $user->setChangePasswordAtNextLogin(true);

        $userName = new UserName();
        $userName->setGivenName($firstName);
        $userName->setFamilyName($lastName ?: $firstName);
        $user->setName($userName);

        try {
            $result = $this->directory->users->insert($user);
            Log::info("Google Workspace user created successfully: " . $result->getPrimaryEmail());
            return $result->getPrimaryEmail();
        } catch (Exception $e) {
            Log::error("Failed to create Google Workspace user ({$email}): " . $e->getMessage());
            
            // Periksa jika error karena email sudah exist (Duplicate)
            if (str_contains($e->getMessage(), 'Entity already exists')) {
                Log::warning("User email {$email} already exists in Google Workspace. Modifying prefix.");
                // Jika ingin membuat retry mechanism untuk suffix angka, bisa lempar exception khusus
                throw new Exception("EmailAlreadyExists");
            }
            return null;
        }
    }
}