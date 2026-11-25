<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\LiveClass;
use App\Models\Option;
use App\Models\Order;
use App\Models\Question;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* ---------------- Users ---------------- */
        $admin = User::create([
            'name' => "Alom's English Center Admin", 'email' => 'admin@lms.test', 'password' => Hash::make('password'),
            'role' => 'admin', 'phone' => '', 'designation' => 'Administrator',
            'bio' => "Administrator account for Alom's English Center.",
        ]);

        $instructors = collect([
            ['Nusrat Jahan', 'Spoken English', 'English Language Instructor'],
            ['Tanvir Ahmed', 'Grammar & Writing', 'English Grammar Instructor'],
            ['Sadia Islam', 'IELTS Preparation', 'IELTS Preparation Coach'],
        ])->map(fn ($row, $i) => User::create([
            'name' => $row[0], 'email' => 'teacher'.$i.'@lms.test', 'password' => Hash::make('password'),
            'role' => 'instructor', 'designation' => $row[2], 'phone' => '',
            'bio' => $row[1].' instructor at Alom’s English Center.',
        ]));

        $students = collect([
            'Tasannun Tarin', 'Mubina Khanom', 'Shamim Shohan', 'Afrina Jahan',
            'Borhan Uddin Shifat', 'Rakib Hasan', 'Sumaiya Akter', 'Naimur Rahman',
        ])->map(fn ($name, $i) => User::create([
            'name' => $name,
            'email' => $i === 0 ? 'student@lms.test' : 'student'.$i.'@lms.test',
            'password' => Hash::make('password'),
            'role' => 'student', 'phone' => '0180000000'.$i,
        ]));

        /* ---------------- Settings ---------------- */
        foreach ([
            'site_name' => "Alom's English Center",
            'site_tagline' => 'Build confidence in English through practical lessons and guided learning.',
            'site_email' => '',
            'site_phone' => '',
            'site_address' => '',
            'bkash_number' => '',
            'nagad_number' => '',
            'currency' => '৳',
            'facebook' => '',
            'youtube' => '',
            'instagram' => '',
            'certificate_signature' => "Alom's English Center Admin",
            'about_text' => 'Alom’s English Center helps learners improve speaking, grammar, vocabulary, listening and writing through structured lessons and guided practice.',
        ] as $key => $value) {
            Setting::set($key, $value);
        }

        /* ---------------- Categories ---------------- */
        $categories = collect([
            ['English Foundations', '📘', 'Build a strong foundation in everyday English, sentence structure and essential vocabulary.'],
            ['Spoken English', '🗣️', 'Develop fluency, pronunciation, listening and real-life conversation skills.'],
            ['Grammar & Writing', '✍️', 'Learn practical grammar, sentence construction, paragraphs, emails and essays.'],
            ['IELTS Preparation', '🌍', 'Prepare for IELTS Listening, Reading, Writing and Speaking.'],
            ['Kids English', '🧒', 'Fun, age-appropriate English vocabulary, phonics and simple conversations.'],
            ['Professional English', '💼', 'Improve workplace communication, presentations, interviews and business writing.'],
        ])->map(fn ($row, $i) => Category::create([
            'name' => $row[0], 'icon' => $row[1], 'description' => $row[2],
            'position' => $i + 1, 'is_active' => true,
        ]));

        /* ---------------- English learning demo courses ---------------- */
        $courseBlueprints = [
            [
                'title' => 'Spoken English for Beginners',
                'category' => 1, 'price' => 3000, 'discount' => 2500, 'featured' => true, 'level' => 'beginner',
                'subtitle' => 'Start speaking English confidently with everyday words, useful sentences and guided conversation practice.',
                'outcomes' => ['Introduce yourself and talk about daily routines', 'Ask and answer common questions naturally', 'Build confidence through guided speaking practice'],
                'sections' => [
                    'Getting Started with English' => ['Greetings and introductions', 'Introducing yourself and your family', 'Talking about daily routines'],
                    'Everyday Conversations' => ['Asking for information politely', 'Shopping, directions and common situations', 'Practice: a short self-introduction'],
                    'Speaking with Confidence' => ['Making simple sentences quickly', 'Common speaking mistakes and corrections', 'Final speaking practice'],
                ],
            ],
            [
                'title' => 'English Grammar from Basic to Advanced',
                'category' => 2, 'price' => 3500, 'discount' => 2990, 'featured' => true, 'level' => 'beginner',
                'subtitle' => 'Understand parts of speech, tenses, sentence structure and practical grammar through clear examples.',
                'outcomes' => ['Identify and use the parts of speech', 'Choose correct tense and verb forms', 'Write accurate sentences with fewer mistakes'],
                'sections' => [
                    'Grammar Foundations' => ['Nouns, pronouns and articles', 'Verbs, adjectives and adverbs', 'Prepositions and conjunctions'],
                    'Sentence Building' => ['Subject, verb and object', 'All essential English tenses', 'Questions, negatives and auxiliaries'],
                    'Accuracy in English' => ['Subject-verb agreement', 'Common grammar errors', 'Grammar revision and practice'],
                ],
            ],
            [
                'title' => 'English Vocabulary & Pronunciation',
                'category' => 0, 'price' => 1800, 'discount' => null, 'featured' => true, 'level' => 'beginner',
                'subtitle' => 'Grow your usable vocabulary and practise clear, natural English pronunciation.',
                'outcomes' => ['Learn and use high-frequency everyday vocabulary', 'Recognise syllables and basic word stress', 'Use new words in meaningful sentences'],
                'sections' => [
                    'Useful Everyday Vocabulary' => ['People, places and objects', 'Food, travel and daily activities', 'Word families and collocations'],
                    'Clear Pronunciation' => ['English sounds and phonemic awareness', 'Syllables and word stress', 'Sentence stress and intonation'],
                    'Using New Words' => ['Synonyms and antonyms in context', 'Phrasal verbs for daily communication', 'Vocabulary review and speaking task'],
                ],
            ],
            [
                'title' => 'IELTS Preparation: Four Skills',
                'category' => 3, 'price' => 6000, 'discount' => 4990, 'featured' => true, 'level' => 'intermediate',
                'subtitle' => 'A structured introduction to IELTS Listening, Reading, Writing and Speaking with timed practice.',
                'outcomes' => ['Understand the IELTS test format and question types', 'Practise all four skills with a study plan', 'Review common task strategies and mistakes'],
                'sections' => [
                    'IELTS Listening & Reading' => ['Listening question types and note taking', 'Reading skimming and scanning', 'Timed listening and reading practice'],
                    'IELTS Writing' => ['Academic Task 1 overview', 'Task 2 essay structure and coherence', 'Writing review checklist'],
                    'IELTS Speaking' => ['Speaking Part 1: familiar topics', 'Speaking Part 2: long turn', 'Speaking Part 3: extending answers'],
                ],
            ],
            [
                'title' => 'English Writing: Paragraph, Email & Essay',
                'category' => 2, 'price' => 2200, 'discount' => 1800, 'featured' => false, 'level' => 'intermediate',
                'subtitle' => 'Learn to organise ideas and write clear paragraphs, formal emails and short essays.',
                'outcomes' => ['Write a focused paragraph with a clear topic sentence', 'Structure formal and informal emails', 'Plan and write coherent short essays'],
                'sections' => [
                    'Writing Foundations' => ['Sentence clarity and punctuation', 'Topic sentences and supporting details', 'Paragraph unity and coherence'],
                    'Practical Writing' => ['Formal and informal email writing', 'Applications and short messages', 'Descriptive and opinion paragraphs'],
                    'Essay Skills' => ['Planning and outlining an essay', 'Introduction, body and conclusion', 'Editing and proofreading'],
                ],
            ],
            [
                'title' => 'English for Job Interviews & Workplace',
                'category' => 5, 'price' => 2500, 'discount' => null, 'featured' => false, 'level' => 'intermediate',
                'subtitle' => 'Practise professional introductions, interview answers, meetings and clear workplace communication.',
                'outcomes' => ['Give a confident professional self-introduction', 'Answer common interview questions clearly', 'Write polite workplace emails and messages'],
                'sections' => [
                    'Professional Communication' => ['Introducing yourself professionally', 'Polite requests and clear responses', 'Telephone and online meeting etiquette'],
                    'Job Interview Practice' => ['Talking about skills and experience', 'Answering strengths and weaknesses questions', 'Mock interview preparation'],
                    'Workplace Writing' => ['Professional email structure', 'Meeting notes and follow-up messages', 'Presentation language basics'],
                ],
            ],
            [
                'title' => 'Free English Starter Pack',
                'category' => 0, 'price' => 0, 'discount' => null, 'featured' => false, 'level' => 'beginner',
                'subtitle' => 'A free first step into English learning with greetings, simple sentences and essential vocabulary.',
                'outcomes' => ['Use common greetings and polite expressions', 'Build simple everyday sentences', 'Learn essential words for daily communication'],
                'sections' => [
                    'Your First English Lessons' => ['Greetings and polite expressions', 'Common words and simple sentences', 'Introduce yourself in English'],
                ],
            ],
        ];

        foreach ($courseBlueprints as $i => $bp) {
            $course = Course::create([
                'title' => $bp['title'],
                'subtitle' => $bp['subtitle'],
                'category_id' => $categories[$bp['category']]->id,
                'instructor_id' => $i % 2 === 0 ? $admin->id : $instructors[$i % $instructors->count()]->id,
                'description' => $bp['subtitle']."\n\nThis course is designed for practical English learning. Study the lesson notes, complete guided exercises, review your quiz results and join scheduled teacher-led practice sessions.",
                'requirements' => "Basic understanding of the previous class syllabus.\nA notebook and a stable internet connection.",
                'outcomes' => $bp['outcomes'],
                'price' => $bp['price'],
                'discount_price' => $bp['discount'],
                'level' => $bp['level'],
                'language' => 'Bangla & English',
                'is_published' => true,
                'is_featured' => $bp['featured'],
                'has_certificate' => true,
                'starts_at' => now()->addDays(3 + $i),
                'seat_limit' => 200,
            ]);

            $lessonPosition = 1;
            foreach ($bp['sections'] as $sectionTitle => $lessons) {
                $section = $course->sections()->create([
                    'title' => $sectionTitle,
                    'position' => $course->sections()->count() + 1,
                ]);

                foreach ($lessons as $j => $lessonTitle) {
                    Lesson::create([
                        'course_id' => $course->id,
                        'section_id' => $section->id,
                        'title' => $lessonTitle,
                        'type' => 'text',
                        'video_url' => null,
                        'content' => "In this lesson, you will practise **{$lessonTitle}** with clear examples and guided activities.\n\nLearning steps:\n1. Understand the key idea and useful language.\n2. Read the examples and notice how the language is used.\n3. Practise by creating your own sentences.\n4. Review your work and correct common mistakes.\n\nPractice task: write five original sentences using today’s lesson.",
                        'duration_minutes' => rand(12, 55),
                        'position' => $lessonPosition++,
                        'is_free_preview' => $lessonPosition <= 3,
                        'is_published' => true,
                    ]);
                }
            }

            $course->update(['duration_minutes' => $course->lessons()->sum('duration_minutes')]);

            // Course exams
            $exam = Exam::create([
                'course_id' => $course->id,
                'title' => 'Chapter-wise MCQ Test — '.Str::words($course->title, 4, ''),
                'description' => 'Answer all questions. Each correct answer gives 1 mark, each wrong answer deducts 0.25 marks.',
                'duration_minutes' => 20,
                'pass_marks' => 6,
                'negative_marking' => 0.25,
                'is_published' => true,
                'is_free' => false,
                'shuffle_questions' => true,
            ]);

            $this->seedQuestions($exam);

            // Announcement + live class
            Announcement::create([
                'course_id' => $course->id,
                'title' => 'Welcome to '.Str::words($course->title, 3, ''),
                'body' => 'Welcome to the course. Review the first lessons and complete the practice activities. Check your student dashboard for scheduled teacher-led sessions.',
                'is_published' => true,
            ]);

            LiveClass::create([
                'course_id' => $course->id,
                'title' => 'Doubt solving: '.Str::limit($course->title, 38),
                'meeting_url' => 'https://meet.jit.si/aloms-english-center-demo',
                'platform' => 'jitsi',
                'starts_at' => now()->addDays(2),
                'duration_minutes' => 90,
            ]);
        }

        /* ---------------- E-books ---------------- */
        $books = [
            ['Everyday English Vocabulary Builder', 250, 80, false],
            ['English Grammar Quick Reference', 0, 36, true],
            ['IELTS Writing Practice Workbook', 400, 120, false],
            ['Spoken English Conversation Practice', 0, 45, true],
        ];
        foreach ($books as $b) {
            Ebook::create([
                'title' => $b[0], 'description' => 'A carefully prepared resource by Alom\'s English Center academic team. Includes solved examples, shortcuts and practice questions.',
                'price' => $b[1], 'pages' => $b[2], 'is_free' => $b[3], 'is_published' => true,
            ]);
        }

        /* ---------------- Coupons ---------------- */
        Coupon::create(['code' => 'EID25', 'type' => 'percent', 'value' => 25, 'min_amount' => 1000, 'usage_limit' => 100, 'is_active' => true]);
        Coupon::create(['code' => 'FLAT500', 'type' => 'fixed', 'value' => 500, 'min_amount' => 2000, 'usage_limit' => 0, 'is_active' => true]);

        /* ---------------- Enrollments, orders & reviews ---------------- */
        $allCourses = Course::all();
        foreach ($students as $index => $student) {
            $picked = $allCourses->shuffle()->take(rand(1, 3));

            foreach ($picked as $course) {
                $price = $course->final_price;

                $order = Order::create([
                    'user_id' => $student->id,
                    'subtotal' => $price,
                    'discount' => 0,
                    'total' => $price,
                    'status' => 'paid',
                    'name' => $student->name,
                    'phone' => $student->phone,
                    'email' => $student->email,
                ]);
                $order->items()->create([
                    'item_type' => 'course', 'item_id' => $course->id,
                    'title' => $course->title, 'price' => $price, 'qty' => 1,
                ]);
                $order->payments()->create([
                    'gateway' => 'bkash', 'transaction_id' => 'TRX'.Str::upper(Str::random(8)),
                    'sender_number' => $student->phone, 'amount' => $price, 'status' => 'verified',
                ]);

                Enrollment::create([
                    'user_id' => $student->id, 'course_id' => $course->id, 'order_id' => $order->id,
                    'price_paid' => $price, 'status' => 'active', 'enrolled_at' => now()->subDays(rand(1, 40)),
                ]);

                // mark a few lessons complete
                $lessons = $course->lessons()->take(rand(0, 4))->get();
                foreach ($lessons as $lesson) {
                    \App\Models\LessonProgress::create([
                        'user_id' => $student->id, 'lesson_id' => $lesson->id,
                        'watched_seconds' => rand(120, 900), 'completed_at' => now()->subDays(rand(1, 20)),
                    ]);
                }

                Review::create([
                    'course_id' => $course->id,
                    'user_id' => $student->id,
                    'rating' => rand(4, 5),
                    'comment' => collect([
                        'Speaking practice helped me feel more confident when I use English in everyday situations.',
                        'The grammar lessons are clear and the practice activities help me understand my mistakes.',
                        'প্রতিটি ক্লাসে নতুন শব্দ ও বাক্য ব্যবহার করতে শিখছি।',
                        'The writing lessons helped me organise my ideas and write more clearly.',
                        'IELTS speaking practice is organised and gives me a clear way to improve.',
                    ])->random(),
                    'is_approved' => true,
                ]);
            }
        }

        // One pending review for the moderation demo
        Review::create([
            'course_id' => $allCourses->first()->id,
            'user_id' => $students->last()->id,
            'rating' => 5,
            'comment' => 'The lesson examples are clear and the practice tasks are useful.',
            'is_approved' => false,
        ]);

        // Pending manual payment order
        $pending = Order::create([
            'user_id' => $students->first()->id,
            'subtotal' => 2990, 'discount' => 0, 'total' => 2990,
            'status' => 'pending', 'name' => $students->first()->name,
            'phone' => $students->first()->phone, 'email' => $students->first()->email,
        ]);
        $pending->items()->create([
            'item_type' => 'course', 'item_id' => 2, 'title' => 'English Grammar from Basic to Advanced',
            'price' => 2990, 'qty' => 1,
        ]);
        $pending->payments()->create([
            'gateway' => 'bkash', 'transaction_id' => 'TRXDEMO123',
            'sender_number' => '01800000000', 'amount' => 2990, 'status' => 'pending',
        ]);

        // A certificate for a fully completed student
        $done = Course::find($allCourses->first()->id);
        $student = $students->first();
        foreach ($done->lessons as $lesson) {
            \App\Models\LessonProgress::firstOrCreate(
                ['user_id' => $student->id, 'lesson_id' => $lesson->id],
                ['watched_seconds' => 600, 'completed_at' => now()->subDays(2)]
            );
        }
        \App\Models\Certificate::create([
            'user_id' => $student->id, 'course_id' => $done->id,
            'code' => 'CERT-DEMO1234', 'issued_at' => now()->subDay(),
        ]);

        $this->call(OrganisationSeeder::class);
    }

    private function seedQuestions(Exam $exam): void
    {
        $bank = [
            ['Which greeting is most suitable at 9:00 a.m.?', ['Good morning', 'Good night', 'Good evening', 'Goodbye'], 0, 'Good morning is used in the morning.'],
            ['She _____ to class every day.', ['goes', 'go', 'going', 'gone'], 0, 'With she, he or it in the present simple, the verb usually takes -s or -es.'],
            ['Which sentence is grammatically correct?', ['He does not like coffee.', 'He do not likes coffee.', 'He not like coffee.', 'He does not likes coffee.'], 0, 'After does not, use the base form of the verb: like.'],
            ['What is the closest meaning of “happy”?', ['Glad', 'Angry', 'Tired', 'Afraid'], 0, 'Glad is a synonym of happy.'],
            ['What is the plural form of “child”?', ['Children', 'Childs', 'Childes', 'Childrens'], 0, 'Children is the irregular plural form of child.'],
            ['Choose the correct article: “She ate _____ apple.”', ['an', 'a', 'the', 'no article'], 0, 'Use an before a vowel sound, as in apple.'],
            ['Choose the correct preposition: “I am interested _____ learning English.”', ['in', 'on', 'at', 'for'], 0, 'The expression is interested in.'],
            ['What is the past tense of “go”?', ['Went', 'Goed', 'Gone', 'Going'], 0, 'Went is the simple past form of go.'],
        ];

        shuffle($bank);

        foreach (array_slice($bank, 0, 8) as $i => $row) {
            $question = Question::create([
                'exam_id' => $exam->id,
                'type' => 'mcq',
                'question' => $row[0],
                'explanation' => $row[3],
                'marks' => 1,
                'position' => $i + 1,
            ]);

            $options = $row[1];
            shuffle($options);

            foreach ($options as $index => $text) {
                Option::create([
                    'question_id' => $question->id,
                    'option_text' => $text,
                    'is_correct' => $text === $row[1][0],
                    'position' => $index,
                ]);
            }
        }

        $exam->update(['total_marks' => $exam->questions()->sum('marks')]);
    }
}
