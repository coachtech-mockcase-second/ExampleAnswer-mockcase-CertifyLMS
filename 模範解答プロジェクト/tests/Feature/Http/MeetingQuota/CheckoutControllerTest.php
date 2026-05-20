<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Stripe\Service\Checkout\SessionService;
use Stripe\StripeClient;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_checkout_select(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        MeetingPack::factory()->published()->count(2)->create();
        MeetingPack::factory()->draft()->create(['name' => 'Hidden Draft']);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        $response->assertOk();
        $response->assertViewIs('meeting-quota.checkout-select');
        $response->assertDontSee('Hidden Draft');
    }

    public function test_admin_and_coach_cannot_access_checkout_select(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $this->actingAs($admin)->get(route('meeting-quota.checkout.select'))->assertForbidden();
        $this->actingAs($coach)->get(route('meeting-quota.checkout.select'))->assertForbidden();
    }

    public function test_graduated_student_cannot_purchase(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        $response->assertForbidden();
    }

    public function test_student_can_create_checkout_session_and_redirect(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $plan = MeetingPack::factory()->published()->state(['price' => 12000, 'meeting_count' => 5])->create();

        $sessionId = 'cs_test_'.bin2hex(random_bytes(12));
        $sessionUrl = 'https://checkout.stripe.com/c/pay/'.$sessionId;

        $this->mock(StripeClient::class, function (MockInterface $mock) use ($sessionId, $sessionUrl) {
            $sessionService = Mockery::mock(SessionService::class);
            $session = Session::constructFrom(['id' => $sessionId, 'url' => $sessionUrl]);
            $sessionService->shouldReceive('create')->once()->andReturn($session);
            $mock->checkout = (object) ['sessions' => $sessionService];
        });

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $plan->id,
        ]);

        $response->assertRedirect($sessionUrl);
        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $plan->id,
            'stripe_checkout_session_id' => $sessionId,
            'amount' => 12000,
            'quantity' => 5,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_validates_plan_must_be_published(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draftPlan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $draftPlan->id,
        ]);

        $response->assertSessionHasErrors('meeting_pack_id');
        $this->assertDatabaseMissing('payments', [
            'meeting_pack_id' => $draftPlan->id,
        ]);
    }

    public function test_success_renders_with_payment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $payment = Payment::factory()->succeeded()->state(['user_id' => $student->id])->create();

        $response = $this->actingAs($student)->get(route('meeting-quota.success', [
            'session_id' => $payment->stripe_checkout_session_id,
        ]));

        $response->assertOk();
        $response->assertViewIs('meeting-quota.success');
        $response->assertViewHas('payment', fn ($p) => $p?->id === $payment->id);
    }
}
