<?php

declare(strict_types=1);

namespace Osmium\Services\Clarity\Models;

/**
 * Microsoft Clarity configuration helper.
 *
 * Loads this service's own settings file so theme blocks don't hardcode
 * them, matching the file-based config convention used by the other
 * services (app/config/services/{id}.json.php).
 */
class ClarityConfig
{
    private static ?object $config = null;
    private static string $configPath = 'app/config/services/clarity.json.php';

    /**
     * Falls back to defaults (disabled, no project ID) if the config file is
     * missing, so installing this service stays inert until someone visits
     * its settings page and saves a project ID.
     */
    public static function get(): object
    {
        $configLoaded = self::$config !== null;
        if ($configLoaded) return self::$config;

        $configFile = self::$configPath;

        $configExists = \file_exists($configFile);
        if (!$configExists) {
            self::$config = self::defaults();
            return self::$config;
        }

        $content = \file_get_contents($configFile);
        $jsonStart = \strpos(haystack: $content, needle: '{');

        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) {
            self::$config = self::defaults();
            return self::$config;
        }

        $json = \substr(string: $content, offset: $jsonStart);
        $decoded = \json_decode($json);

        self::$config = $decoded->clarity ?? self::defaults();

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    /**
     * A Clarity project ID is 8-12 letters or digits. Anything else is rejected so a typo
     * (or a pasted snippet) can't end up inside the page's inline script.
     */
    public static function isValidProjectId(string $projectId): bool
    {
        return \preg_match(pattern: '/^[a-z0-9]{8,12}$/i', subject: $projectId) === 1;
    }

    private static function defaults(): object
    {
        return (object) [
            'enabled' => false,
            'projectId' => '',
        ];
    }
}
