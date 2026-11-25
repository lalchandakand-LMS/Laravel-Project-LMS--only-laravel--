<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $branches = [
            ['name' => 'Main Learning Center', 'code' => 'AEC-01', 'address' => '', 'phone' => ''],
            ['name' => 'Online Learning Center', 'code' => 'AEC-02', 'address' => '', 'phone' => ''],
            ['name' => 'Speaking Practice Center', 'code' => 'AEC-03', 'address' => '', 'phone' => ''],
            ['name' => 'Remote Learning', 'code' => 'AEC-04', 'address' => '', 'phone' => ''],
        ];

        $models = collect($branches)->map(fn ($b) => Branch::firstOrCreate(
            ['code' => $b['code']],
            $b + ['email' => strtolower($b['code']).'@alomsenglishcenter.test', 'is_active' => true]
        ));

        // Branch managers
        foreach ($models->take(3) as $i => $branch) {
            $manager = User::firstOrCreate(
                ['email' => 'manager'.($i + 1).'@lms.test'],
                [
                    'name' => ['Dhanmondi Manager', 'Uttara Manager', 'Chattogram Manager'][$i],
                    'password' => $password,
                    'role' => 'branch_manager',
                    'branch_id' => $branch->id,
                    'designation' => 'Branch Manager',
                    'phone' => '+880 1811-20000'.($i + 1),
                    'is_active' => true,
                ]
            );
            $branch->update(['manager_id' => $manager->id]);
        }

        // Assign existing students + instructors to branches
        $students = User::where('role', 'student')->orderBy('id')->get();
        foreach ($students as $i => $student) {
            $student->update(['branch_id' => $models[$i % $models->count()]->id]);
        }
        foreach (User::where('role', 'instructor')->get() as $i => $teacher) {
            $teacher->update(['branch_id' => $models[$i % $models->count()]->id]);
        }

        // Guardians linked to first two students
        $relations = ['father', 'mother'];
        foreach ($students->take(2) as $i => $student) {
            $guardian = User::firstOrCreate(
                ['email' => 'guardian'.($i + 1).'@lms.test'],
                [
                    'name' => $student->name.'\'s Guardian',
                    'password' => $password,
                    'role' => 'guardian',
                    'branch_id' => $student->branch_id,
                    'phone' => '+880 1911-30000'.($i + 1),
                    'is_active' => true,
                ]
            );
            $guardian->children()->syncWithoutDetaching([
                $student->id => ['relation' => $relations[$i] ?? 'guardian'],
            ]);
        }

        // Home page hero sliders
        $slides = [
            [
                'badge' => 'English courses — online learning and guided practice',
                'title' => "Learn English with confidence at Alom's English Center",
                'subtitle' => "Build speaking, grammar, vocabulary and writing skills with structured lessons, practice and teacher support.",
                'image' => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1600&q=80',
                'button_text' => 'Explore courses',
                'button_url' => '/courses',
                'theme' => 'emerald',
                'sort_order' => 1,
            ],
            [
                'badge' => 'English grammar and writing',
                'title' => 'Build accurate English for study and work',
                'subtitle' => 'Learn sentence structure, practical grammar, paragraph writing and clear communication with guided practice.',
                'image' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1600&q=80',
                'button_text' => 'Explore grammar courses',
                'button_url' => '/courses',
                'theme' => 'light',
                'sort_order' => 2,
            ],
            [
                'badge' => 'IELTS and communication skills',
                'title' => 'Prepare for confident English communication',
                'subtitle' => 'Practise listening, reading, writing and speaking with structured lessons and a clear learning plan.',
                'image' => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=1600&q=80',
                'button_text' => 'Explore IELTS preparation',
                'button_url' => '/courses',
                'theme' => 'slate',
                'sort_order' => 3,
            ],
        ];

        foreach ($slides as $slide) {
            Slider::updateOrCreate(['sort_order' => $slide['sort_order']], $slide + ['is_active' => true]);
        }

        // Attach a couple of live classes to the first course (if the teacher module is used)
        if ($course = Course::first()) {
            \App\Models\LiveClass::firstOrCreate(
                ['course_id' => $course->id, 'title' => 'Weekly doubt-solving session'],
                [
                    'platform' => 'jitsi',
                    'meeting_url' => 'https://meet.jit.si/aloms-english-center-demo',
                    'starts_at' => now()->addDays(2)->setTime(20, 0),
                    'duration_minutes' => 60,
                    'is_published' => true,
                ]
            );
        }
    }
}
