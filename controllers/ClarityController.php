<?php

declare(strict_types=1);

namespace Osmium\Services\Clarity\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Clarity\Models\ClarityConfig;

/**
 * Microsoft Clarity settings controller - a full-page form POST/redirect flow,
 * matching the Google Analytics service.
 *
 * Routes:
 *   - index() → /admin/settings/clarity/  (GET shows the form, POST saves it)
 */
class ClarityController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/clarity.json.php';
    private const DEFAULT_CONFIG = <<<'JSON'
        <?php exit(); ?>
        {
            "clarity": {
                "enabled": false,
                "projectId": ""
            }
        }
        JSON;

    public function index(): void
    {
        $isPost = $this->isPost();
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['config']['clarity'] = (array) ClarityConfig::get();
        $this->data['admin']['settingsSaved'] = $_SESSION['clarity_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['clarity_settings_error'] ?? false;
        unset($_SESSION['clarity_settings_saved'], $_SESSION['clarity_settings_error']);

        $this->setView('clarity/index.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['clarity_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/clarity/');
        }

        $enabled = isset($_POST['enabled']);
        $projectId = \trim($_POST['project_id'] ?? '');

        $projectIdValid = ClarityConfig::isValidProjectId($projectId);
        $needsValidId = $enabled || $projectId !== '';
        if ($needsValidId && !$projectIdValid) {
            $_SESSION['clarity_settings_error'] = 'Project ID must be 8-12 letters or digits.';
            $this->redirect('settings/clarity/');
        }

        $this->saveConfig($enabled, $projectId);

        $this->admin->model->changelog->log(
            description: 'Updated Microsoft Clarity settings',
            recordType: 'settings',
        );

        ClarityConfig::clearCache();

        $_SESSION['clarity_settings_saved'] = true;
        $this->redirect('settings/clarity/');
    }

    private function saveConfig(bool $enabled, string $projectId): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $content = $configExists ? \file_get_contents(self::CONFIG_FILE_PATH) : self::DEFAULT_CONFIG;

        $jsonStart = \strpos(haystack: $content, needle: '{');
        $phpHeader = \substr(string: $content, offset: 0, length: $jsonStart);
        $data = \json_decode(\substr(string: $content, offset: $jsonStart), associative: true) ?? [];

        $data['clarity'] = ['enabled' => $enabled, 'projectId' => $projectId];

        $newJson = \json_encode(
            value: $data,
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, $phpHeader . $newJson . "\n");
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
