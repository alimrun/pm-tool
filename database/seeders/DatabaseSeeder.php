<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Users -------------------------------------------------------
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@example.com'],
            [
                'name' => 'Viewer User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_VIEWER,
                'email_verified_at' => now(),
            ]
        );

        // --- Projects ----------------------------------------------------
        $checkout = Project::create(['name' => 'Checkout', 'color' => '#4f46e5', 'description' => 'Storefront checkout & payments.']);
        $mobile = Project::create(['name' => 'Mobile App', 'color' => '#10b981', 'description' => 'iOS & Android apps.']);
        $billing = Project::create(['name' => 'Billing', 'color' => '#e11d48', 'description' => 'Subscriptions & invoicing.']);
        $data = Project::create(['name' => 'Data Platform', 'color' => '#7c3aed', 'description' => 'Analytics & pipelines.']);

        // --- Teams -------------------------------------------------------
        $alpha = Team::create(['name' => 'Team Alpha', 'color' => '#0891b2', 'description' => 'Web platform squad.']);
        $bravo = Team::create(['name' => 'Team Bravo', 'color' => '#d97706', 'description' => 'Mobile squad.']);
        $charlie = Team::create(['name' => 'Team Charlie', 'color' => '#16a34a', 'description' => 'Data & billing squad.']);

        $year = (int) now()->year;

        // --- Releases ----------------------------------------------------
        // Team Alpha: these first two overlap on purpose (Jul 20–30) to demo
        // the double-booking warning and the amber dashboard highlight.
        $this->makeRelease($checkout, $alpha, 'Checkout v2.4', $year, 3, "$year-07-10", "$year-07-30");
        $this->makeRelease($billing, $alpha, 'Billing hotfix', $year, 3, "$year-07-20", "$year-08-05"); // OVERLAP
        $this->makeRelease($checkout, $alpha, 'Checkout v2.5', $year, 4, "$year-10-01", "$year-10-25");

        // Team Bravo: back-to-back, no overlap.
        $this->makeRelease($mobile, $bravo, 'Mobile 3.0', $year, 3, "$year-08-01", "$year-08-28");
        $this->makeRelease($mobile, $bravo, 'Mobile 3.1', $year, 4, "$year-11-03", "$year-11-24");

        // Team Charlie: spread across quarters, no overlap.
        $this->makeRelease($data, $charlie, 'Data Platform GA', $year, 2, "$year-05-05", "$year-06-20");
        $this->makeRelease($billing, $charlie, 'Billing revamp', $year, 3, "$year-09-01", "$year-09-30");
    }

    /**
     * Create a release and tile its window with the four canonical phases.
     */
    private function makeRelease(Project $project, Team $team, string $name, int $year, int $quarter, string $start, string $end): void
    {
        $release = Release::create([
            'project_id' => $project->id,
            'team_id' => $team->id,
            'name' => $name,
            'year' => $year,
            'quarter' => $quarter,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();
        $total = $s->diffInDays($e) + 1;

        // Contiguous phase boundaries (Dev 40% / QA 25% / Retest 15% / Release 20%).
        $devEnd = $s->copy()->addDays((int) floor(($total - 1) * 0.40));
        $qaEnd = $s->copy()->addDays((int) floor(($total - 1) * 0.65));
        $retestEnd = $s->copy()->addDays((int) floor(($total - 1) * 0.80));

        $segments = [
            ['development', $s, $devEnd],
            ['qa', $devEnd->copy()->addDay(), $qaEnd],
            ['retest', $qaEnd->copy()->addDay(), $retestEnd],
            ['release', $retestEnd->copy()->addDay(), $e],
        ];

        $position = 0;
        foreach ($segments as [$phase, $pStart, $pEnd]) {
            // Guard tiny windows so a phase never ends before it starts / exits window.
            if ($pStart->gt($e)) {
                $pStart = $e->copy();
            }
            if ($pEnd->lt($pStart)) {
                $pEnd = $pStart->copy();
            }
            if ($pEnd->gt($e)) {
                $pEnd = $e->copy();
            }

            $release->phases()->create([
                'phase' => $phase,
                'position' => $position++,
                'start_date' => $pStart->toDateString(),
                'end_date' => $pEnd->toDateString(),
            ]);
        }
    }
}
