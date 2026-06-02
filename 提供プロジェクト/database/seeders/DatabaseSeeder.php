<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PlanSeeder::class,
            UserLifecycleSeeder::class,
            MeetingPackSeeder::class,
            CertificationCategorySeeder::class,
            CertificationSeeder::class,
            InvitationSeeder::class,
            EnrollmentSeeder::class,
            MentoringSeeder::class,
            ContentSeeder::class,
            LearningSeeder::class,
            QuizAnsweringSeeder::class,
            MockExamSeeder::class,
            ChatSeeder::class,
            QaBoardSeeder::class,
            AiChatSeeder::class,
            CertificateSeeder::class,
        ]);
    }
}
