<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds demo users, posts (some with images, some private), comments,
 * replies and likes. Safe to re-run: it wipes and recreates demo content.
 *
 * Run: php artisan db:seed --class=DemoSeeder
 * All demo accounts use the password "password123".
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    private array $people = [
        ['Test', 'User', 'test@example.com'],
        ['Alice', 'Smith', 'alice@example.com'],
        ['Bob', 'Jones', 'bob@example.com'],
        ['Carol', 'Nguyen', 'carol@example.com'],
        ['David', 'Okafor', 'david@example.com'],
        ['Emma', 'Rossi', 'emma@example.com'],
        ['Farhan', 'Ahmed', 'farhan@example.com'],
        ['Grace', 'Kim', 'grace@example.com'],
        ['Hugo', 'Martin', 'hugo@example.com'],
        ['Isla', 'Brown', 'isla@example.com'],
    ];

    private array $postTexts = [
        'Just finished my first marathon! Legs are dead but heart is full.',
        'Anyone have recommendations for a good sci-fi book? Just finished Project Hail Mary.',
        'Coffee number three today. Send help. ☕',
        'Sunset from the rooftop tonight was unreal.',
        'Finally deployed the side project I have been hacking on for months. Feedback welcome!',
        'Hot take: tabs are better than spaces. Fight me.',
        'Weekend hike with the crew. 14 km, 900 m elevation, zero regrets.',
        'Made homemade pasta for the first time. It was a mess but delicious.',
        'Grateful for the amazing team I get to work with every day.',
        'Does anyone else talk to their plants? Asking for a friend. 🌱',
        'New personal best at the gym today! Consistency pays off.',
        'Rainy days call for soup and a long movie marathon.',
        'Started learning Spanish on a whim. ¡Hola a todos!',
        'Reminder: drink water, stretch, and log off on time.',
        'The new cafe downstairs makes the best croissants in the city.',
        'Throwback to last summer at the beach. Counting down the days.',
        'Spent the afternoon volunteering at the animal shelter. So many good dogs.',
        'Just adopted a cat. Her name is Pixel and she already runs the house.',
        'Big presentation tomorrow. Wish me luck!',
        'Learned more from one failed project than ten successful ones.',
        'Road trip playlist suggestions? Need 6 hours of music.',
        'Tried rock climbing for the first time. Forearms are on fire.',
        'Our little community garden harvested its first tomatoes!',
        'Reading by the window with tea is my love language.',
        'Celebrating 5 years at the company today. Time flies.',
        'Who else is watching the meteor shower tonight?',
        'Minimal desk setup finally complete. Productivity +100.',
        'Baked sourdough. Named the starter Doughy McDoughface.',
        'Private note to friends: party at mine Saturday, bring snacks!',
        'Morning run along the river. Best way to start the day.',
    ];

    private array $commentTexts = [
        'This is awesome! 🔥', 'Congrats, well deserved!', 'Love this so much.',
        'Wow, looks amazing!', 'Totally agree with you.', 'Haha, same here 😂',
        'Where was this taken?', 'Need to try this soon.', 'You inspire me!',
        'So proud of you!', 'Count me in next time.', 'This made my day.',
        'Great shot!', 'Can you share the recipe?', 'Absolutely beautiful.',
        'I disagree, but respect the take 😄', 'How long did this take you?',
        'Goals right here.', 'Thanks for sharing this!', 'Miss you, let\'s catch up!',
    ];

    private array $replyTexts = [
        'Thank you! 🙏', 'Right?!', 'Haha exactly', 'Will DM you the details.',
        'Next time for sure!', 'Appreciate it!', 'You\'re the best.',
        'Agreed 100%', 'Lol yes', 'Took about a week honestly.',
        'Downtown, near the old bridge.', 'Let\'s do it!', '❤️', 'Same thought!',
    ];

    private array $images = [
        'post_img.png', 'timeline_img.png', 'feed_event1.png',
        'slider1.png', 'slider2.png', 'slider3.png', 'slider4.png',
        'photos1.png', 'photos2.png', 'photos3.png', 'photos4.png',
    ];

    public function run(): void
    {
        mt_srand(42);

        $users = collect($this->people)->map(fn ($p) => User::updateOrCreate(
            ['email' => $p[2]],
            ['first_name' => $p[0], 'last_name' => $p[1], 'password' => self::PASSWORD]
        ));
        $ids = $users->pluck('id')->all();

        // Wipe prior demo content (likes are polymorphic, so no FK cascade).
        Like::whereIn('user_id', $ids)->delete();
        Post::whereIn('user_id', $ids)->each(function (Post $post) {
            $commentIds = Comment::where('post_id', $post->id)->pluck('id');
            $replyIds = Reply::whereIn('comment_id', $commentIds)->pluck('id');
            Like::where('likeable_type', Reply::class)->whereIn('likeable_id', $replyIds)->delete();
            Like::where('likeable_type', Comment::class)->whereIn('likeable_id', $commentIds)->delete();
            Like::where('likeable_type', Post::class)->where('likeable_id', $post->id)->delete();
            $post->delete();
        });

        $imageUrls = $this->publishImages();

        foreach ($this->postTexts as $i => $text) {
            $author = $ids[$i % count($ids)];
            $postedAt = Carbon::now()->subMinutes(mt_rand(10, 60 * 24 * 14));

            $post = new Post([
                'user_id' => $author,
                'content' => $text,
                'image_url' => $imageUrls && mt_rand(1, 100) <= 45 ? $imageUrls[array_rand($imageUrls)] : null,
                'is_private' => str_starts_with($text, 'Private') || mt_rand(1, 100) <= 8,
            ]);
            $post->created_at = $postedAt;
            $post->updated_at = $postedAt;
            $post->save();

            $this->like($post, Post::class, $ids, mt_rand(0, count($ids) - 1));

            foreach (range(1, mt_rand(0, 6)) as $_) {
                if (mt_rand(1, 100) <= 15) {
                    continue;
                }
                $commentAt = $postedAt->copy()->addMinutes(mt_rand(1, 600));
                $comment = new Comment([
                    'post_id' => $post->id,
                    'user_id' => $ids[array_rand($ids)],
                    'content' => $this->commentTexts[array_rand($this->commentTexts)],
                ]);
                $comment->created_at = $commentAt;
                $comment->updated_at = $commentAt;
                $comment->save();
                $this->like($comment, Comment::class, $ids, mt_rand(0, 5));

                foreach (range(1, mt_rand(0, 3)) as $_r) {
                    if (mt_rand(1, 100) <= 30) {
                        continue;
                    }
                    $replyAt = $commentAt->copy()->addMinutes(mt_rand(1, 300));
                    $reply = new Reply([
                        'comment_id' => $comment->id,
                        // Author often replies to comments on their own post.
                        'user_id' => mt_rand(0, 1) ? $author : $ids[array_rand($ids)],
                        'content' => $this->replyTexts[array_rand($this->replyTexts)],
                    ]);
                    $reply->created_at = $replyAt;
                    $reply->updated_at = $replyAt;
                    $reply->save();
                    $this->like($reply, Reply::class, $ids, mt_rand(0, 3));
                }
            }
        }

        $this->command?->info(sprintf(
            'Demo data: %d users, %d posts, %d comments, %d replies, %d likes. Password: %s',
            count($ids),
            Post::whereIn('user_id', $ids)->count(),
            Comment::whereIn('user_id', $ids)->count(),
            Reply::whereIn('user_id', $ids)->count(),
            Like::whereIn('user_id', $ids)->count(),
            self::PASSWORD,
        ));
    }

    private function like($model, string $type, array $ids, int $count): void
    {
        $likers = $ids;
        shuffle($likers);
        foreach (array_slice($likers, 0, $count) as $userId) {
            Like::create(['user_id' => $userId, 'likeable_id' => $model->id, 'likeable_type' => $type]);
        }
    }

    /** Copy frontend sample images into public storage; returns their URLs. */
    private function publishImages(): array
    {
        $source = base_path('../assets/images');
        $urls = [];
        foreach ($this->images as $file) {
            $path = "$source/$file";
            if (!File::exists($path)) {
                continue;
            }
            $target = "posts/demo_$file";
            Storage::disk('public')->put($target, File::get($path));
            $urls[] = Storage::url($target);
        }
        return $urls;
    }
}
