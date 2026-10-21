<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chatbot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The fixed-rules chatbot: every screen, button, typed value and decision. */
class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private const FALLBACK = 'Sorry, I did not understand that. Please choose one of the options below.';

    private Chatbot $bot;

    private array $state = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bot = new Chatbot;
        $this->state = [];
    }

    /** Send one message and return the bot's messages. */
    private function say(string $text, ?User $user = null): array
    {
        $r = $this->bot->handle($text, $user, $this->state);
        $this->state = $r['state'];

        return $r['messages'];
    }

    private function last(array $messages): array
    {
        return $messages[array_key_last($messages)];
    }

    public function test_guest_welcome_menu(): void
    {
        $m = $this->say('hi');
        $this->assertCount(1, $m);
        $this->assertSame('Welcome to the Saltmarsh Hotel. You are not signed in. How can we help?', $m[0]['text']);
        $this->assertSame(['Rooms and rates', 'Restaurant', 'Create an account'], $m[0]['buttons']);
        $this->assertSame('unregistered', $this->state['flow']);
        $this->assertSame('guest_welcome', $this->state['node']);
    }

    public function test_first_message_that_is_not_a_keyword_shows_fallback_then_menu(): void
    {
        $m = $this->say('what is this');
        $this->assertSame(self::FALLBACK, $m[0]['text']);
        $this->assertSame('guest_welcome', $this->state['node']);
    }

    public function test_rooms_and_restaurant_paths_and_buttons_are_case_insensitive(): void
    {
        $this->say('hi');
        $m = $this->say('ROOMS AND RATES');
        $this->assertStringStartsWith('We have Standard, Deluxe and Sea View rooms', $m[0]['text']);
        $this->assertSame(['Create an account', 'Main menu'], $m[0]['buttons']);

        $this->say('Main menu');
        $m = $this->say('restaurant');
        $this->assertStringStartsWith('The Saltmarsh Grill is open 07:00 to 22:00.', $m[0]['text']);
        $this->assertSame(['View menu', 'Main menu'], $m[0]['buttons']);

        $m = $this->say('View menu');
        $this->assertStringStartsWith('Tonight: grilled line fish', $m[0]['text']);
        $this->assertSame(['Main menu'], $m[0]['buttons']);

        $m = $this->say('Main menu');
        $this->assertSame('guest_welcome', $this->state['node']);
    }

    public function test_unknown_button_repeats_the_same_screen_after_fallback(): void
    {
        $this->say('hi');
        $this->say('Restaurant');
        $m = $this->say('pizza please');
        $this->assertCount(2, $m);
        $this->assertSame(self::FALLBACK, $m[0]['text']);
        $this->assertSame('restaurant_info', $this->state['node']);
        $this->assertSame(['View menu', 'Main menu'], $m[1]['buttons']);
    }

    public function test_create_account_with_registered_email_goes_to_already_registered(): void
    {
        User::factory()->create(['email' => 'guest@example.com']);
        $this->say('hi');
        $m = $this->say('Create an account');
        $this->assertStringStartsWith('Please enter your email address', $m[0]['text']);
        $this->assertSame([], $m[0]['buttons']);

        $m = $this->say('Guest@Example.com');
        $this->assertStringStartsWith('That email address already has an account.', $m[0]['text']);
        $this->assertSame('already_registered', $this->state['node']);

        // After an end screen any message gives the fallback and the menu again.
        $m = $this->say('ok thanks');
        $this->assertSame(self::FALLBACK, $m[0]['text']);
        $this->assertSame('guest_welcome', $this->state['node']);
    }

    public function test_create_account_with_new_email_asks_name_then_creates(): void
    {
        $this->say('hi');
        $this->say('Rooms and rates');
        $this->say('Create an account');
        $m = $this->say('new.person@example.com');
        $this->assertSame('Please enter your first name.', $m[0]['text']);
        $m = $this->say('Alex');
        $this->assertStringStartsWith('Thanks, your account is created.', $m[0]['text']);
        $this->assertSame('account_created', $this->state['node']);
        // The chatbot never really creates accounts.
        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    public function test_invalid_typed_values_repeat_the_question(): void
    {
        $this->say('hi');
        $this->say('Create an account');
        foreach (['not-an-email', 'a@', str_repeat('x', 130).'@example.com'] as $bad) {
            $m = $this->say($bad);
            $this->assertCount(1, $m);
            $this->assertStringStartsWith('Please enter your email address', $m[0]['text']);
            $this->assertSame('create_email', $this->state['node']);
        }
        $this->say('someone@example.com');
        foreach (['123', '<script>', '-Alex'] as $bad) {
            $this->say($bad);
            $this->assertSame('create_name', $this->state['node']);
        }
        $this->say("Zoë O'Neil");
        $this->assertSame('account_created', $this->state['node']);
    }

    public function test_restart_keywords_work_from_any_screen(): void
    {
        foreach (['hi', 'hello', 'menu', 'start', 'restart', '  MENU  '] as $word) {
            $this->say('hi');
            $this->say('Create an account');
            $m = $this->say($word);
            $this->assertCount(1, $m, $word);
            $this->assertSame('guest_welcome', $this->state['node'], $word);
        }
    }

    public function test_registered_welcome_uses_first_name(): void
    {
        $user = User::factory()->create(['name' => 'Douglas Bailey']);
        $m = $this->say('hi', $user);
        $this->assertSame('Welcome back, Douglas. What would you like to do today?', $m[0]['text']);
        $this->assertSame(['Book a room', 'Reserve a table', 'My bookings'], $m[0]['buttons']);
        $this->assertArrayNotHasKey('first_name', $this->state['ctx']);
    }

    public function test_room_booking_when_free(): void
    {
        $user = User::factory()->create();
        $this->say('hi', $user);
        $m = $this->say('Book a room', $user);
        $this->assertStringStartsWith('Please enter your check-in date', $m[0]['text']);
        $m = $this->say('12 Dec, 2 nights', $user);
        $this->assertStringStartsWith('Rooms free for those dates', $m[0]['text']);
        $this->assertSame(['Standard', 'Sea View'], $m[0]['buttons']);
        $m = $this->say('Sea View', $user);
        $this->assertSame('Please check: Sea View, 12 Dec, 2 nights. Shall I book it?', $m[0]['text']);
        $this->assertSame(['Book it', 'Change dates', 'Main menu'], $m[0]['buttons']);
        $m = $this->say('Book it', $user);
        $this->assertStringStartsWith('Booked. Your reference is SH-2041', $m[0]['text']);
    }

    public function test_room_review_change_dates_and_main_menu(): void
    {
        $user = User::factory()->create();
        $this->say('hi', $user);
        $this->say('Book a room', $user);
        $this->say('3 March, 1 night', $user);
        $this->say('Standard', $user);
        $this->say('Change dates', $user);
        $this->assertSame('room_dates', $this->state['node']);
        $this->say('Jan 5, 3 nights', $user);
        $this->say('Standard', $user);
        $this->say('Main menu', $user);
        $this->assertSame('welcome_back', $this->state['node']);
    }

    public function test_fully_booked_dates_go_to_no_rooms(): void
    {
        $user = User::factory()->create();
        $this->say('hi', $user);
        $this->say('Book a room', $user);
        $m = $this->say('24 Dec, 2 nights', $user);
        $this->assertSame('Sorry, we are fully booked on those dates.', $m[0]['text']);
        $this->assertSame(['Change dates', 'Main menu'], $m[0]['buttons']);
        $this->say('Change dates', $user);
        $this->assertSame('room_dates', $this->state['node']);
        $this->say('20 Dec', $user);
        $this->assertSame('no_rooms', $this->state['node']);
        $this->say('Main menu', $user);
        $this->assertSame('welcome_back', $this->state['node']);
    }

    public function test_bad_dates_repeat_the_question(): void
    {
        $user = User::factory()->create();
        $this->say('hi', $user);
        $this->say('Book a room', $user);
        foreach (['tomorrow', '31 Feb', '12 Dec, 40 nights', '0 Dec'] as $bad) {
            $this->say($bad, $user);
            $this->assertSame('room_dates', $this->state['node'], $bad);
        }
    }

    public function test_table_reservation_needs_a_digit(): void
    {
        $user = User::factory()->create();
        $this->say('hi', $user);
        $this->say('Reserve a table', $user);
        $this->say('Friday evening', $user);
        $this->assertSame('table_details', $this->state['node']);
        $m = $this->say('Fri 19:30, 4 people', $user);
        $this->assertStringStartsWith('Your table at The Saltmarsh Grill is reserved.', $m[0]['text']);
    }

    public function test_my_bookings_and_reception(): void
    {
        $user = User::factory()->create();
        $this->say('hi', $user);
        $m = $this->say('My bookings', $user);
        $this->assertSame(['Talk to reception', 'Main menu'], $m[0]['buttons']);
        $m = $this->say('Talk to reception', $user);
        $this->assertStringStartsWith('A receptionist will reply here shortly.', $m[0]['text']);
    }

    public function test_signing_in_mid_conversation_switches_route(): void
    {
        $this->say('hi');
        $this->say('Create an account');
        $user = User::factory()->create(['name' => 'Alex Smith']);
        $m = $this->say('Book a room', $user);
        // A route switch starts fresh: not a known screen yet, so fallback then welcome back.
        $this->assertSame(self::FALLBACK, $m[0]['text']);
        $this->assertSame('Welcome back, Alex. What would you like to do today?', $m[1]['text']);
        $this->assertSame('registered', $this->state['flow']);
    }

    public function test_parse_dates(): void
    {
        $this->assertSame(['day' => 12, 'month' => 12, 'nights' => 2], $this->bot->parseDates('12 Dec, 2 nights'));
        $this->assertSame(['day' => 12, 'month' => 12, 'nights' => 3], $this->bot->parseDates('Dec 12, 3 nights'));
        $this->assertSame(['day' => 1, 'month' => 1, 'nights' => 1], $this->bot->parseDates('1 january'));
        $this->assertSame(['day' => 29, 'month' => 2, 'nights' => 1], $this->bot->parseDates('29 Feb'));
        $this->assertNull($this->bot->parseDates('31 Feb'));
        $this->assertNull($this->bot->parseDates('31 Apr'));
        $this->assertNull($this->bot->parseDates('next week'));
        $this->assertNull($this->bot->parseDates('12 Dec, 0 nights'));
    }

    public function test_flow_files_are_consistent(): void
    {
        foreach (['unregistered', 'registered'] as $id) {
            $flow = $this->bot->flow($id);
            foreach ($flow['out'] as $from => $edges) {
                $this->assertArrayHasKey($from, $flow['nodes']);
                foreach ($edges as $e) {
                    $this->assertArrayHasKey($e['to'], $flow['nodes'], "{$id}: {$from} -> {$e['to']}");
                }
            }
            foreach ($flow['nodes'] as $node) {
                if (in_array($node['kind'], ['menu', 'input', 'decision', 'start'], true)) {
                    $this->assertNotEmpty($flow['out'][$node['id']] ?? [], "{$id}: {$node['id']} has no way out");
                }
            }
        }
    }
}
