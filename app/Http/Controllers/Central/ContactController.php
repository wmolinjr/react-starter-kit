<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('central/contact/index');
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Send notification email to support
        Mail::raw(
            "Name: {$validated['name']}\nEmail: {$validated['email']}\nSubject: {$validated['subject']}\n\nMessage:\n{$validated['message']}",
            fn ($mail) => $mail
                ->to(config('mail.from.address'))
                ->replyTo($validated['email'], $validated['name'])
                ->subject("[Contact Form] {$validated['subject']}")
        );

        return back()->with('success', __('marketing.contact.success'));
    }
}
