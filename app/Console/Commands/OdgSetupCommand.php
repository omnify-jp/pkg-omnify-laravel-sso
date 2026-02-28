<?php

declare(strict_types=1);

namespace Omnify\Core\Console\Commands;

use Illuminate\Console\Command;

class OdgSetupCommand extends Command
{
    protected $signature = 'odg:setup';

    protected $description = 'ODG (Omnify Dev Guide) MCP サーバーを .mcp.json に設定する';

    public function handle(): int
    {
        $projectRoot = base_path();
        $mcpJsonPath = $projectRoot.'/.mcp.json';
        $binaryPath = 'vendor/omnifyjp/pkg-omnify-laravel-core/bin/odg';

        // Check if binary exists
        if (! file_exists($projectRoot.'/'.$binaryPath)) {
            if (! $this->output->isQuiet()) {
                $this->warn('ODG binary not found at '.$binaryPath.'. Skipping setup.');
            }

            return self::SUCCESS;
        }

        // Read existing .mcp.json or start fresh
        $config = [];
        if (file_exists($mcpJsonPath)) {
            $content = file_get_contents($mcpJsonPath);
            $config = json_decode($content, true) ?? [];
        }

        // Ensure mcpServers key exists
        if (! isset($config['mcpServers'])) {
            $config['mcpServers'] = [];
        }

        // Add/update odg entry
        $config['mcpServers']['odg'] = [
            'command' => $binaryPath,
            'args' => [],
        ];

        // Write back with pretty print
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        file_put_contents($mcpJsonPath, $json);

        if (! $this->output->isQuiet()) {
            $this->info('ODG MCP server configured in .mcp.json');
        }

        return self::SUCCESS;
    }
}
