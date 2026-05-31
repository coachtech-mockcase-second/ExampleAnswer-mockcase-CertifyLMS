<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InvitationMail の envelope / content / queue 実装を検証する Unit テスト。
 * Mailable オブジェクトを直接生成し、subject / view 名 / with データの構造を assert する。
 * 送信トリガー検証は Feature(UseCases) 側で扱うため、本ファイルは「Mailable 自体の組み立て」に責務を絞る。
 */
class InvitationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_subject_is_certify_lms_invitation(): void
    {
        // Arrange
        $invitation = Invitation::factory()->pending()->create();

        // Act
        $envelope = (new InvitationMail($invitation))->envelope();

        // Assert
        $this->assertSame('Certify LMS への招待', $envelope->subject, '件名は固定文言『Certify LMS への招待』のはず');
    }

    public function test_envelope_to_address_uses_invitation_email(): void
    {
        // Arrange
        $invitation = Invitation::factory()->pending()->create([
            'email' => 'invitee@example.test',
        ]);

        // Act
        $envelope = (new InvitationMail($invitation))->envelope();

        // Assert
        $this->assertCount(1, $envelope->to);
        $this->assertSame('invitee@example.test', $envelope->to[0]->address);
    }

    public function test_content_uses_invitation_markdown_view(): void
    {
        // Arrange
        $invitation = Invitation::factory()->pending()->create();

        // Act
        $content = (new InvitationMail($invitation))->content();

        // Assert
        $this->assertSame('emails.invitation', $content->markdown, 'markdown view は emails.invitation のはず');
    }

    public function test_content_with_data_includes_invitation_and_inviter_and_role_label(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->pending()->create([
            'invited_by_user_id' => $admin->id,
            'expires_at' => '2026-06-01 12:00:00',
        ]);

        // Act
        $content = (new InvitationMail($invitation))->content();

        // Assert
        $this->assertArrayHasKey('invitation', $content->with);
        $this->assertArrayHasKey('invitedBy', $content->with);
        $this->assertArrayHasKey('roleLabel', $content->with);
        $this->assertArrayHasKey('expiresAt', $content->with);
        $this->assertArrayHasKey('url', $content->with);

        $this->assertSame($invitation->id, $content->with['invitation']->id);
        $this->assertSame($admin->id, $content->with['invitedBy']->id);
        $this->assertIsString($content->with['roleLabel'], 'roleLabel は UserRole::label() 由来の文字列のはず');
        $this->assertIsString($content->with['url'], 'url は InvitationTokenService 経由で生成された署名付き URL のはず');
    }

    public function test_mailable_implements_should_queue_for_async_delivery(): void
    {
        // Arrange
        $invitation = Invitation::factory()->pending()->create();

        // Act
        $mailable = new InvitationMail($invitation);

        // Assert
        $this->assertInstanceOf(
            ShouldQueue::class,
            $mailable,
            '招待メールは ShouldQueue 実装で非同期キュー送信されるはず (送信遅延がユーザー体感に影響しないように)',
        );
    }
}
