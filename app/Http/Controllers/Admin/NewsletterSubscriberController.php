<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request)
    {
        $query = NewsletterSubscriber::query()
            ->when($request->filled('q'), fn ($builder) => $builder->where('email', 'like', '%'.trim($request->string('q')->toString()).'%'))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->input('status')));

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($query) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Email', 'Name', 'Status', 'Source', 'Confirmed at', 'Unsubscribed at', 'Created at']);
                $query->orderBy('id')->cursor()->each(function (NewsletterSubscriber $subscriber) use ($output) {
                    $values = [
                        $subscriber->email, $subscriber->name, $subscriber->status, $subscriber->source,
                        $subscriber->confirmed_at?->toDateTimeString(),
                        $subscriber->unsubscribed_at?->toDateTimeString(),
                        $subscriber->created_at?->toDateTimeString(),
                    ];
                    $values = array_map(function ($value) {
                        $value = (string) ($value ?? '');
                        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                    }, $values);
                    fputcsv($output, $values);
                });
                fclose($output);
            }, 'newsletter-subscribers-'.now()->format('Y-m-d-His').'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return view('admin.marketing.newsletter.index', [
            'subscribers' => $query->latest()->paginate(30)->withQueryString(),
            'counts' => collect(NewsletterSubscriber::STATUSES)->mapWithKeys(
                fn ($status) => [$status => NewsletterSubscriber::where('status', $status)->count()]
            ),
        ]);
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber record deleted.');
    }
}
