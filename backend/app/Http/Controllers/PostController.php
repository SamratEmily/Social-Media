<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Comment;
use App\Models\Reply;
use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Abort with 404 unless the current user may see the post.
     * Private posts are visible only to their owner. 404 (not 403)
     * avoids revealing that a private post exists.
     */
    private function ensureCanView(Post $post): void
    {
        if ($post->is_private && (int) $post->user_id !== (int) auth()->id()) {
            abort(404);
        }
    }

    public function index()
    {
        $userId = auth()->id();
        
        // Get all public posts OR private posts by the current user
        $posts = Post::with(['user:id,first_name,last_name,email', 'likes.user:id,first_name,last_name'])
            ->where(function ($query) use ($userId) {
                $query->where('is_private', false)
                      ->orWhere('user_id', $userId);
            })
            ->withCount('likes')
            ->with(['comments' => function($query) {
                $query->orderBy('created_at', 'asc')
                      ->withCount('likes')
                      ->with(['user:id,first_name,last_name', 'likes.user:id,first_name,last_name', 'replies' => function($q) {
                          $q->withCount('likes')
                            ->with(['user:id,first_name,last_name', 'likes.user:id,first_name,last_name']);
                      }]);
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($post) use ($userId) {
                $isLiked = $post->likes->contains('user_id', $userId);
                $likers = $post->likes->map(function ($like) {
                    return [
                        'id' => $like->user->id,
                        'first_name' => $like->user->first_name,
                        'last_name' => $like->user->last_name,
                        'full_name' => $like->user->first_name . ' ' . $like->user->last_name,
                    ];
                });

                $comments = $post->comments->map(function ($comment) use ($userId) {
                    $isLiked = $comment->likes->contains('user_id', $userId);
                    $replies = $comment->replies->map(function ($reply) use ($userId) {
                        $isLiked = $reply->likes->contains('user_id', $userId);
                        return [
                            'id' => $reply->id,
                            'content' => $reply->content,
                            'created_at' => $reply->created_at->diffForHumans(),
                            'likes_count' => $reply->likes_count,
                            'is_liked' => $isLiked,
                            'user' => [
                                'id' => $reply->user->id,
                                'first_name' => $reply->user->first_name,
                                'last_name' => $reply->user->last_name,
                                'full_name' => $reply->user->first_name . ' ' . $reply->user->last_name,
                            ],
                        ];
                    });

                    return [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'created_at' => $comment->created_at->diffForHumans(),
                        'likes_count' => $comment->likes_count,
                        'is_liked' => $isLiked,
                        'replies' => $replies,
                        'user' => [
                            'id' => $comment->user->id,
                            'first_name' => $comment->user->first_name,
                            'last_name' => $comment->user->last_name,
                            'full_name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                        ],
                    ];
                });
                
                return [
                    'id' => $post->id,
                    'content' => $post->content,
                    'image_url' => $post->image_url,
                    'is_private' => $post->is_private,
                    'created_at' => $post->created_at->diffForHumans(),
                    'likes_count' => $post->likes_count,
                    'is_liked' => $isLiked,
                    'likers' => $likers,
                    'comments' => $comments,
                    'user' => [
                        'id' => $post->user->id,
                        'first_name' => $post->user->first_name,
                        'last_name' => $post->user->last_name,
                        'full_name' => $post->user->first_name . ' ' . $post->user->last_name,
                    ]
                ];
            });

        return response()->json([
            'success' => true,
            'posts' => $posts
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required_without:image|nullable|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'is_private' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            // store() generates a random, extension-safe filename; never trust
            // the client-supplied name (path chars, collisions, overwrites).
            $imagePath = $request->file('image')->store('posts', 'public');
            $imageUrl = Storage::url($imagePath);
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'content' => $request->content ?? '',
            'image_url' => $imageUrl,
            'is_private' => $request->boolean('is_private', false),
        ]);

        $post->load(['user:id,first_name,last_name,email', 'likes.user:id,first_name,last_name']);
        $isLiked = false;
        $likers = [];

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'post' => [
                'id' => $post->id,
                'content' => $post->content,
                'image_url' => $post->image_url,
                'is_private' => $post->is_private,
                'created_at' => $post->created_at->diffForHumans(),
                'likes_count' => 0,
                'is_liked' => $isLiked,
                'likers' => $likers,
                'user' => [
                    'id' => $post->user->id,
                    'first_name' => $post->user->first_name,
                    'last_name' => $post->user->last_name,
                    'full_name' => $post->user->first_name . ' ' . $post->user->last_name,
                ]
            ]
        ], 201);
    }

    public function like(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        $this->ensureCanView($post);
        $userId = auth()->id();

        // Check if already liked
        $like = Like::where('user_id', $userId)
            ->where('likeable_id', $post->id)
            ->where('likeable_type', Post::class)
            ->first();

        if ($like) {
            $like->delete();
            $isLiked = false;
        } else {
            Like::create([
                'user_id' => $userId,
                'likeable_id' => $post->id,
                'likeable_type' => Post::class,
            ]);
            $isLiked = true;
        }

        $post->load(['likes.user:id,first_name,last_name']);
        $likers = $post->likes->map(function ($like) {
            return [
                'id' => $like->user->id,
                'first_name' => $like->user->first_name,
                'last_name' => $like->user->last_name,
                'full_name' => $like->user->first_name . ' ' . $like->user->last_name,
            ];
        });

        return response()->json([
            'success' => true,
            'is_liked' => $isLiked,
            'likes_count' => $post->likes->count(),
            'likers' => $likers,
        ]);
    }

    public function getComments($id)
    {
        $post = Post::findOrFail($id);
        $this->ensureCanView($post);
        $userId = auth()->id();

        $comments = Comment::where('post_id', $post->id)
            ->with(['user:id,first_name,last_name,email', 'replies.user:id,first_name,last_name,email', 'likes.user:id,first_name,last_name'])
            ->withCount('likes')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($comment) use ($userId) {
                $isLiked = $comment->likes->contains('user_id', $userId);
                $likers = $comment->likes->map(function ($like) {
                    return [
                        'id' => $like->user->id,
                        'first_name' => $like->user->first_name,
                        'last_name' => $like->user->last_name,
                        'full_name' => $like->user->first_name . ' ' . $like->user->last_name,
                    ];
                });

                $replies = $comment->replies->map(function ($reply) use ($userId) {
                    $isLiked = $reply->likes->contains('user_id', $userId);
                    $likers = $reply->likes->map(function ($like) {
                        return [
                            'id' => $like->user->id,
                            'first_name' => $like->user->first_name,
                            'last_name' => $like->user->last_name,
                            'full_name' => $like->user->first_name . ' ' . $like->user->last_name,
                        ];
                    });

                    return [
                        'id' => $reply->id,
                        'content' => $reply->content,
                        'created_at' => $reply->created_at->diffForHumans(),
                        'likes_count' => $reply->likes->count(),
                        'is_liked' => $isLiked,
                        'likers' => $likers,
                        'user' => [
                            'id' => $reply->user->id,
                            'first_name' => $reply->user->first_name,
                            'last_name' => $reply->user->last_name,
                            'full_name' => $reply->user->first_name . ' ' . $reply->user->last_name,
                        ],
                    ];
                });

                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at' => $comment->created_at->diffForHumans(),
                    'likes_count' => $comment->likes_count,
                    'is_liked' => $isLiked,
                    'likers' => $likers,
                    'replies' => $replies,
                    'user' => [
                        'id' => $comment->user->id,
                        'first_name' => $comment->user->first_name,
                        'last_name' => $comment->user->last_name,
                        'full_name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'comments' => $comments,
        ]);
    }

    public function addComment(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        $this->ensureCanView($post);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);

        $comment->load(['user:id,first_name,last_name,email', 'likes.user:id,first_name,last_name']);
        $userId = auth()->id();
        $isLiked = false;
        $likers = [];

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'created_at' => $comment->created_at->diffForHumans(),
                'likes_count' => 0,
                'is_liked' => $isLiked,
                'likers' => $likers,
                'replies' => [],
                'user' => [
                    'id' => $comment->user->id,
                    'first_name' => $comment->user->first_name,
                    'last_name' => $comment->user->last_name,
                    'full_name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                ],
            ]
        ], 201);
    }

    public function likeComment(Request $request, $id)
    {
        $comment = Comment::with('post')->findOrFail($id);
        $this->ensureCanView($comment->post);
        $userId = auth()->id();

        $like = Like::where('user_id', $userId)
            ->where('likeable_id', $comment->id)
            ->where('likeable_type', Comment::class)
            ->first();

        if ($like) {
            $like->delete();
            $isLiked = false;
        } else {
            Like::create([
                'user_id' => $userId,
                'likeable_id' => $comment->id,
                'likeable_type' => Comment::class,
            ]);
            $isLiked = true;
        }

        $comment->load(['likes.user:id,first_name,last_name']);
        $likers = $comment->likes->map(function ($like) {
            return [
                'id' => $like->user->id,
                'first_name' => $like->user->first_name,
                'last_name' => $like->user->last_name,
                'full_name' => $like->user->first_name . ' ' . $like->user->last_name,
            ];
        });

        return response()->json([
            'success' => true,
            'is_liked' => $isLiked,
            'likes_count' => $comment->likes->count(),
            'likers' => $likers,
        ]);
    }

    public function addReply(Request $request, $commentId)
    {
        $comment = Comment::with('post')->findOrFail($commentId);
        $this->ensureCanView($comment->post);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $reply = Reply::create([
            'comment_id' => $comment->id,
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);

        $reply->load(['user:id,first_name,last_name,email', 'likes.user:id,first_name,last_name']);
        $userId = auth()->id();
        $isLiked = false;
        $likers = [];

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'reply' => [
                'id' => $reply->id,
                'content' => $reply->content,
                'created_at' => $reply->created_at->diffForHumans(),
                'likes_count' => 0,
                'is_liked' => $isLiked,
                'likers' => $likers,
                'user' => [
                    'id' => $reply->user->id,
                    'first_name' => $reply->user->first_name,
                    'last_name' => $reply->user->last_name,
                    'full_name' => $reply->user->first_name . ' ' . $reply->user->last_name,
                ],
            ]
        ], 201);
    }

    public function likeReply(Request $request, $id)
    {
        $reply = Reply::with('comment.post')->findOrFail($id);
        $this->ensureCanView($reply->comment->post);
        $userId = auth()->id();

        $like = Like::where('user_id', $userId)
            ->where('likeable_id', $reply->id)
            ->where('likeable_type', Reply::class)
            ->first();

        if ($like) {
            $like->delete();
            $isLiked = false;
        } else {
            Like::create([
                'user_id' => $userId,
                'likeable_id' => $reply->id,
                'likeable_type' => Reply::class,
            ]);
            $isLiked = true;
        }

        $reply->load(['likes.user:id,first_name,last_name']);
        $likers = $reply->likes->map(function ($like) {
            return [
                'id' => $like->user->id,
                'first_name' => $like->user->first_name,
                'last_name' => $like->user->last_name,
                'full_name' => $like->user->first_name . ' ' . $like->user->last_name,
            ];
        });

        return response()->json([
            'success' => true,
            'is_liked' => $isLiked,
            'likes_count' => $reply->likes->count(),
            'likers' => $likers,
        ]);
    }
}
