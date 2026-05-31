<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\UserStatus;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    public function test_enum_lists_four_lifecycle_values(): void
    {
        $values = array_map(fn (UserStatus $s) => $s->value, UserStatus::cases());

        $this->assertEqualsCanonicalizing(
            ['invited', 'in_progress', 'graduated', 'withdrawn'],
            $values,
        );
    }

    public function test_japanese_labels(): void
    {
        $this->assertSame('招待中', UserStatus::Invited->label());
        $this->assertSame('受講中', UserStatus::InProgress->label());
        $this->assertSame('卒業', UserStatus::Graduated->label());
        $this->assertSame('退会済', UserStatus::Withdrawn->label());
    }
}
