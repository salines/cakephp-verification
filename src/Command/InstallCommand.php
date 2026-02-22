<?php
declare(strict_types=1);

namespace CakeVerification\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use CakeVerification\Security\CryptoFactory;
use Throwable;

final class InstallCommand extends Command
{
    /**
     * Build the option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Parser
     * @return \Cake\Console\ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->setDescription('Install verification config into the app and set AES-GCM key.');

        return $parser;
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io Console IO
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $pluginRoot = dirname(__DIR__, 2);
        $source = $pluginRoot . '/config/verification.php';
        $target = ROOT . '/config/verification.php';

        if (!is_file($source)) {
            $io->error('Plugin config file not found: ' . $source);

            return Command::CODE_ERROR;
        }

        if (is_file($target)) {
            $io->warning('Config already exists, not overwriting: ' . $target);

            return Command::CODE_SUCCESS;
        }

        if (!copy($source, $target)) {
            $error = error_get_last();
            $message = $error['message'] ?? 'Unknown error';
            $io->error('Failed to copy config file to: ' . $target . ' (' . $message . ')');

            return Command::CODE_ERROR;
        }

        $aesKey = $this->generateKey('aesgcm');
        $sodiumKey = $this->generateKey('sodium');
        $contents = file_get_contents($target);
        if ($contents === false) {
            $io->error('Failed to read copied config file.');

            return Command::CODE_ERROR;
        }

        $updated = $contents;
        $pattern = "/env\\('VERIFICATION_AESGCM_KEY',\\s*'[^']*'\\)/";
        $replacement = "env('VERIFICATION_AESGCM_KEY', '" . $aesKey . "')";
        $updated = preg_replace($pattern, $replacement, $updated, 1, $aesCount);
        if (!is_string($updated)) {
            $io->error('Failed to update AES-GCM key in config.');

            return Command::CODE_ERROR;
        }

        if ($aesCount === 0) {
            $io->warning('AES-GCM key placeholder not found. Key not inserted.');
        }

        $pattern = "/env\\('VERIFICATION_SODIUM_KEY',\\s*'[^']*'\\)/";
        $replacement = "env('VERIFICATION_SODIUM_KEY', '" . $sodiumKey . "')";
        $updated = preg_replace($pattern, $replacement, $updated, 1, $sodiumCount);
        if (!is_string($updated)) {
            $io->error('Failed to update Sodium key in config.');

            return Command::CODE_ERROR;
        }

        if ($sodiumCount === 0) {
            $io->warning('Sodium key placeholder not found. Key not inserted.');
        }

        if (file_put_contents($target, $updated) === false) {
            $io->error('Failed to write updated config file.');

            return Command::CODE_ERROR;
        }

        $io->success('Verification config installed at ' . $target);
        $io->success('AES-GCM key set to: ' . $aesKey);
        $io->success('Sodium key set to: ' . $sodiumKey);

        return Command::CODE_SUCCESS;
    }

    /**
     * Generate a cryptographic key with fallback for missing extensions.
     *
     * @param string $driver Driver name ('aesgcm' or 'sodium').
     * @return string Base64-encoded key.
     */
    private function generateKey(string $driver): string
    {
        // Try CryptoFactory first (requires sodium for 'sodium' driver)
        try {
            return CryptoFactory::generateKey($driver);
        } catch (Throwable) {
            // Fallback: generate raw bytes manually
            $length = $driver === 'sodium' ? 32 : 32;

            return base64_encode(random_bytes($length));
        }
    }
}
