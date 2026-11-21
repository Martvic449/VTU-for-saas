<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class HelpCentreController
{
    private function audience(Request $request): string
    {
        $user = $request->user();
        return $user->hasAnyRole(['tenant-owner', 'tenant-staff', 'tenant-admin']) ? 'tenants' : 'customers';
    }

    private function canManage(Request $request): void
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
    }

    public function index(Request $request)
    {
        $audience = $this->audience($request);
        $query = DB::table('knowledge_articles')->leftJoin('knowledge_categories', 'knowledge_categories.id', '=', 'knowledge_articles.category_id')
            ->select('knowledge_articles.*', 'knowledge_categories.name as category_name')
            ->where('knowledge_articles.status', 'published')->whereNotNull('knowledge_articles.published_at')
            ->where('knowledge_articles.published_at', '<=', now())
            ->whereIn('knowledge_articles.audience', [$audience, 'both']);
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($term) { $q->where('knowledge_articles.title', 'like', $term)->orWhere('knowledge_articles.excerpt', 'like', $term)->orWhere('knowledge_articles.content', 'like', $term); });
        }
        if ($request->filled('category')) $query->where('knowledge_categories.slug', $request->string('category')->toString());
        $articles = $query->orderByDesc('knowledge_articles.published_at')->paginate(12)->withQueryString();
        $categories = DB::table('knowledge_categories')->orderBy('sort_order')->orderBy('name')->get();
        return view('support.knowledge-index', compact('articles', 'categories', 'audience'));
    }

    public function show(Request $request, string $slug)
    {
        $audience = $this->audience($request);
        $article = DB::table('knowledge_articles')->leftJoin('knowledge_categories', 'knowledge_categories.id', '=', 'knowledge_articles.category_id')
            ->select('knowledge_articles.*', 'knowledge_categories.name as category_name')
            ->where('knowledge_articles.slug', $slug)->where('knowledge_articles.status', 'published')
            ->whereNotNull('knowledge_articles.published_at')->where('knowledge_articles.published_at', '<=', now())
            ->whereIn('knowledge_articles.audience', [$audience, 'both'])->first();
        abort_unless($article, 404);
        $feedback = DB::table('knowledge_article_feedback')->where('article_id', $article->id)->where('user_id', $request->user()->id)->value('helpful');
        return view('support.knowledge-show', compact('article', 'feedback'));
    }

    public function feedback(Request $request, int $article): RedirectResponse
    {
        $data = $request->validate(['helpful' => ['required', 'boolean']]);
        $record = DB::table('knowledge_articles')->where('id', $article)->where('status', 'published')->first();
        abort_unless($record, 404);
        abort_unless(in_array($record->audience, [$this->audience($request), 'both'], true), 404);
        DB::table('knowledge_article_feedback')->updateOrInsert(
            ['article_id' => $article, 'user_id' => $request->user()->id],
            ['helpful' => (bool) $data['helpful'], 'updated_at' => now(), 'created_at' => now()]
        );
        return back()->with('status', 'Thank you for your feedback.');
    }

    public function admin(Request $request)
    {
        $this->canManage($request);
        $articles = DB::table('knowledge_articles')->leftJoin('knowledge_categories', 'knowledge_categories.id', '=', 'knowledge_articles.category_id')
            ->select('knowledge_articles.*', 'knowledge_categories.name as category_name')->orderByDesc('knowledge_articles.updated_at')->paginate(15, ['*'], 'articles');
        $announcements = DB::table('platform_announcements')->leftJoin('tenants', 'tenants.id', '=', 'platform_announcements.tenant_id')
            ->select('platform_announcements.*', 'tenants.name as tenant_name')->orderByDesc('platform_announcements.created_at')->paginate(15, ['*'], 'announcements');
        $categories = DB::table('knowledge_categories')->orderBy('sort_order')->orderBy('name')->get();
        $tenants = DB::table('tenants')->whereIn('status', ['active', 'pending'])->orderBy('name')->get();
        $users = DB::table('users')->select('id', 'name', 'email')->orderBy('name')->limit(500)->get();
        return view('platform.content-management', compact('articles', 'announcements', 'categories', 'tenants', 'users'));
    }

    public function saveCategory(Request $request): RedirectResponse
    {
        $this->canManage($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:knowledge_categories,name'], 'description' => ['nullable', 'string', 'max:500']]);
        $slug = Str::slug($data['name']);
        abort_if(DB::table('knowledge_categories')->where('slug', $slug)->exists(), 422, 'A category with a similar name already exists.');
        DB::table('knowledge_categories')->insert(['name' => $data['name'], 'slug' => $slug, 'description' => $data['description'] ?? null, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Knowledge category created.');
    }

    public function saveArticle(Request $request, ?int $article = null): RedirectResponse
    {
        $this->canManage($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string', 'min:20', 'max:50000'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'audience' => ['required', Rule::in(['customers', 'tenants', 'both'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $slug = Str::slug($data['title']);
        $base = $slug;
        $suffix = 2;
        while (DB::table('knowledge_articles')->where('slug', $slug)->when($article, fn ($q) => $q->where('id', '!=', $article))->exists()) $slug = $base.'-'.$suffix++;
        $values = ['category_id' => $data['category_id'] ?? null, 'title' => $data['title'], 'slug' => $slug, 'excerpt' => $data['excerpt'] ?? null, 'content' => $data['content'], 'audience' => $data['audience'], 'status' => $data['status'], 'published_at' => $data['status'] === 'published' ? now() : null, 'updated_at' => now()];
        if ($article) {
            abort_unless(DB::table('knowledge_articles')->where('id', $article)->exists(), 404);
            DB::table('knowledge_articles')->where('id', $article)->update($values);
        } else {
            $values += ['author_id' => $request->user()->id, 'created_at' => now()];
            DB::table('knowledge_articles')->insert($values);
        }
        return back()->with('status', 'Knowledge article saved.');
    }

    public function deleteArticle(Request $request, int $article): RedirectResponse
    {
        $this->canManage($request);
        DB::table('knowledge_articles')->where('id', $article)->delete();
        return back()->with('status', 'Knowledge article deleted.');
    }

    public function saveAnnouncement(Request $request, ?int $announcement = null): RedirectResponse
    {
        $this->canManage($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:1000'],
            'audience' => ['required', Rule::in(['customers', 'tenants', 'both'])],
            'priority' => ['required', Rule::in(['normal', 'important', 'critical'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_dismissible' => ['nullable', 'boolean'],
        ]);
        $values = ['title' => $data['title'], 'message' => $data['message'], 'audience' => $data['audience'], 'priority' => $data['priority'], 'status' => $data['status'], 'tenant_id' => $data['tenant_id'] ?? null, 'user_id' => $data['user_id'] ?? null, 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null, 'is_dismissible' => (bool) ($data['is_dismissible'] ?? false), 'updated_at' => now()];
        if ($announcement) {
            abort_unless(DB::table('platform_announcements')->where('id', $announcement)->exists(), 404);
            DB::table('platform_announcements')->where('id', $announcement)->update($values);
        } else {
            $values += ['created_by' => $request->user()->id, 'created_at' => now()];
            DB::table('platform_announcements')->insert($values);
        }
        return back()->with('status', 'Announcement saved.');
    }

    public function deleteAnnouncement(Request $request, int $announcement): RedirectResponse
    {
        $this->canManage($request);
        DB::table('platform_announcements')->where('id', $announcement)->delete();
        return back()->with('status', 'Announcement deleted.');
    }

    public function dismissAnnouncement(Request $request, int $announcement): RedirectResponse
    {
        $dismissed = $request->session()->get('dismissed_announcements', []);
        $dismissed[] = $announcement;
        $request->session()->put('dismissed_announcements', array_values(array_unique($dismissed)));
        return back();
    }
}
