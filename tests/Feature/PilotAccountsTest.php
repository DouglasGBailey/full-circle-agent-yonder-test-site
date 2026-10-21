<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** pilot:accounts never writes passwords into the repo. */
class PilotAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_an_out_file_inside_the_repo(): void
    {
        $this->artisan('pilot:accounts', ['--out' => base_path('storage/pw.txt')])->assertFailed();
        $this->assertFileDoesNotExist(base_path('storage/pw.txt'));
        $this->assertSame(0, User::count());
    }

    public function test_writes_accounts_to_a_private_file_outside_the_repo(): void
    {
        $dir = sys_get_temp_dir().'/fca-test-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $out = $dir.'/pilot-accounts.txt';
        $this->artisan('pilot:accounts', ['--out' => $out])->assertSuccessful();
        $this->assertSame(4, User::count());
        $this->assertSame('0600', substr(sprintf('%o', fileperms($out)), -4));
        $this->assertStringContainsString('reviewer.pilot@example.com', file_get_contents($out));

        // Re-running keeps passwords unless --reset.
        $hash = User::where('email', 'guest@example.com')->value('password');
        $this->artisan('pilot:accounts')->assertSuccessful();
        $this->assertSame($hash, User::where('email', 'guest@example.com')->value('password'));
        unlink($out);
        rmdir($dir);
    }
}
