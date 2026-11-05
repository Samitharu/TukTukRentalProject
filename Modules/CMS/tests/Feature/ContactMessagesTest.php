<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Modules\CMS\Mail\ContactMessageReceivedMail;
use Modules\CMS\Models\ContactSubmission;
use Modules\Localization\Models\Locale;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]);
    config(['booking.admin_notification_emails' => ['owner@tuktuk.test']]);

    Permission::findOrCreate('cms.view');
    Permission::findOrCreate('cms.manage');

    $this->reader = User::factory()->create();
    $this->reader->givePermissionTo('cms.view');
    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo(['cms.view', 'cms.manage']);
});

function contactForm(array $overrides = []): array
{
    return ['name' => 'Jane Traveller', 'email' => 'jane@example.test', 'subject' => 'Tuk tuk for 5 days', 'message' => "Is it free in December?\nWe are two people.", ...$overrides];
}

function contactMessage(array $overrides = []): ContactSubmission
{
    return ContactSubmission::query()->create(['name' => 'Jane Traveller', 'email' => 'jane@example.test', 'subject' => 'Tuk tuk for 5 days', 'message' => 'Is it free in December?', 'locale' => 'en', ...$overrides]);
}

// ---- The Contact page -------------------------------------------------------

it('saves a message and emails staff, with replies going to the customer', function (): void {
    Mail::fake();

    $this->post('/en/contact', contactForm())->assertRedirect()->assertSessionHas('status');

    $saved = ContactSubmission::query()->sole();

    expect($saved->name)->toBe('Jane Traveller')
        ->and($saved->is_read)->toBeFalse();

    Mail::assertQueued(ContactMessageReceivedMail::class, fn (ContactMessageReceivedMail $mail) => $mail->contactMessage->is($saved)
        && $mail->hasTo('owner@tuktuk.test')
        && $mail->hasReplyTo('jane@example.test'));
});

it('writes the email with the message and a link to the control panel', function (): void {
    $message = contactMessage(['message' => "Is it free in December\nWe are two people"]);

    $html = (new ContactMessageReceivedMail($message))->render();

    expect($html)
        ->toContain('Jane Traveller')
        ->toContain('Is it free in December')
        ->toContain('We are two people')
        ->toContain("/control-panel/messages/{$message->id}");

    expect((new ContactMessageReceivedMail($message))->envelope()->subject)->toBe('New website message from Jane Traveller');
});

it('does not let a message turn into links in the staff inbox', function (): void {
    $message = contactMessage(['message' => '[Pay here](https://evil.example) <script>alert(1)</script>']);

    expect((new ContactMessageReceivedMail($message))->render())
        ->not->toContain('href="https://evil.example"')
        ->not->toContain('<script>alert(1)');
});

it('still saves the message when the email cannot be queued', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('queue is down'));

    $this->post('/en/contact', contactForm())->assertRedirect()->assertSessionHas('status');

    expect(ContactSubmission::query()->count())->toBe(1);
});

it('saves the message without an email when no staff address is set', function (): void {
    Mail::fake();
    config(['booking.admin_notification_emails' => []]);

    $this->post('/en/contact', contactForm())->assertRedirect();

    expect(ContactSubmission::query()->count())->toBe(1);
    Mail::assertNothingQueued();
});

it('turns away bots and incomplete forms', function (): void {
    Mail::fake();

    $this->post('/en/contact', contactForm(['website' => 'http://spam.example']))->assertSessionHasErrors('website');
    $this->post('/en/contact', contactForm(['message' => '']))->assertSessionHasErrors('message');
    $this->post('/en/contact', contactForm(['email' => 'not-an-email']))->assertSessionHasErrors('email');

    expect(ContactSubmission::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

// ---- The control panel ------------------------------------------------------

it('lists messages newest first, with the unread count in the menu', function (): void {
    contactMessage(['name' => 'Older Person', 'is_read' => true]);
    contactMessage(['name' => 'Newer Person']);

    $this->actingAs($this->reader)->get('/control-panel/messages')
        ->assertOk()
        ->assertSeeInOrder(['Newer Person', 'Older Person'])
        ->assertSee('1 unread')
        ->assertSee('Is it free in December?');

    $this->get('/control-panel')->assertOk()->assertSee('1 unread');
});

it('says so when there are no messages', function (): void {
    $this->actingAs($this->reader)->get('/control-panel/messages')
        ->assertOk()
        ->assertSee('No messages yet')
        ->assertDontSee('unread');
});

it('shows the whole message, marks it read, and offers a reply', function (): void {
    $message = contactMessage(['message' => "First line\nSecond <b>line</b>"]);

    $this->actingAs($this->reader)->get("/control-panel/messages/{$message->id}")
        ->assertOk()
        ->assertSee('Jane Traveller')
        ->assertSee('Tuk tuk for 5 days')
        ->assertSee("First line\nSecond &lt;b&gt;line&lt;/b&gt;", false)
        ->assertSee('mailto:jane@example.test?subject=Re%3A%20Tuk%20tuk%20for%205%20days', false)
        ->assertDontSee('Mark as unread');

    expect($message->refresh()->is_read)->toBeTrue();

    $this->get('/control-panel/messages')->assertDontSee('unread');
});

it('lets managers mark a message unread or remove it', function (): void {
    $message = contactMessage(['is_read' => true]);

    $this->actingAs($this->manager)->get("/control-panel/messages/{$message->id}")->assertSee('Mark as unread')->assertSee('data-confirm', false);

    $this->post("/control-panel/messages/{$message->id}/unread")->assertRedirect('/control-panel/messages');
    expect($message->refresh()->is_read)->toBeFalse();

    $this->delete("/control-panel/messages/{$message->id}")->assertRedirect('/control-panel/messages');
    expect(ContactSubmission::query()->count())->toBe(0);
});

it('keeps messages from anyone without permission', function (): void {
    $message = contactMessage();
    $stranger = User::factory()->create();

    $this->get('/control-panel/messages')->assertRedirect();

    $this->actingAs($stranger)->get('/control-panel/messages')->assertForbidden();
    $this->get("/control-panel/messages/{$message->id}")->assertForbidden();
    expect($message->refresh()->is_read)->toBeFalse();

    $this->actingAs($this->reader)->post("/control-panel/messages/{$message->id}/unread")->assertForbidden();
    $this->delete("/control-panel/messages/{$message->id}")->assertForbidden();

    expect(ContactSubmission::query()->count())->toBe(1);
});
