<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates the pilot's sign-in accounts with random passwords.
 *
 * Passwords never go in the repo: they are printed once and, with --out,
 * written to a file outside the repo with permissions 600. An existing
 * account keeps its password unless --reset is given.
 */
class PilotAccounts extends Command
{
    protected $signature = 'pilot:accounts
        {--out= : Write the sign-in details to this file (chmod 600); must be outside the repo}
        {--reset : Give existing accounts new passwords too}';

    protected $description = 'Create or update the Full Circle Agent pilot accounts';

    /** The pilot testers, plus guest@example.com which the chatbot test data needs. */
    public const ACCOUNTS = [
        ['name' => 'Guest Example', 'email' => 'guest@example.com'],
        ['name' => 'Douglas Bailey', 'email' => 'douglas.pilot@example.com'],
        ['name' => 'Yonder Reviewer', 'email' => 'reviewer.pilot@example.com'],
        ['name' => 'Yonder Requester', 'email' => 'requester.pilot@example.com'],
    ];

    public function handle(): int
    {
        $out = $this->option('out');
        if ($out !== null) {
            $real = realpath(dirname($out));
            if ($real === false) {
                $this->error("The folder for {$out} does not exist.");

                return self::FAILURE;
            }
            if (str_starts_with($real.'/', base_path().'/')) {
                $this->error('Refusing to write passwords inside the repo. Choose a path outside '.base_path().'.');

                return self::FAILURE;
            }
        }

        $lines = [];
        foreach (self::ACCOUNTS as $acc) {
            $user = User::where('email', $acc['email'])->first();
            if ($user && ! $this->option('reset')) {
                $lines[] = "{$acc['email']}  (unchanged; use --reset for a new password)";

                continue;
            }
            $password = Str::password(20, symbols: false);
            User::updateOrCreate(['email' => $acc['email']], [
                'name' => $acc['name'],
                'password' => $password,
            ]);
            $lines[] = "{$acc['email']}  password: {$password}";
        }

        $text = 'Saltmarsh Hotel pilot accounts ('.config('app.url').")\n"
            .'Generated '.now('Africa/Johannesburg')->format('Y-m-d H:i')." SAST\n\n"
            .implode("\n", $lines)."\n";
        if ($out === null) {
            // No file asked for: show the details once, here.
            $this->line($text);

            return self::SUCCESS;
        }

        $old = umask(0077);
        file_put_contents($out, $text);
        umask($old);
        chmod($out, 0600);
        $this->info(count($lines).' accounts. Sign-in details written to '.$out.' (chmod 600), not printed.');

        return self::SUCCESS;
    }
}
