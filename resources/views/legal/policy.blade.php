@extends('layouts.app')
@section('title', $title)
@section('content')
<section class="mx-auto max-w-4xl px-4 py-14">
    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
        <p class="text-xs font-extrabold uppercase tracking-[.2em] text-teal-600">{{ \App\Models\Setting::get('site_name', "Alom's English Center") }}</p>
        <h1 class="mt-3 text-3xl font-extrabold text-slate-900">{{ $title }}</h1>
        <p class="mt-3 text-sm text-slate-500">Information for learners, parents and website visitors.</p>
        @if ($policy === 'terms')
            <div class="prose prose-slate mt-8 max-w-none">
                <h2>Using this website and learning platform</h2><p>By accessing this website or using an account, you agree to use the platform lawfully and to provide accurate account information. Keep your login credentials secure and notify the center if you suspect unauthorized access.</p>
                <h2>Courses, enrolment and access</h2><p>Course descriptions, schedules, fees and access conditions are displayed with the relevant course or batch. Enrolment and learning access are subject to successful registration and any required payment verification.</p>
                <h2>Payments and account conduct</h2><p>Payments must be made through the payment methods presented at checkout or confirmed by the center. Do not misuse course materials, attempt unauthorized access, or disrupt other learners.</p>
                <h2>Changes and contact</h2><p>Course content, schedules and these terms may be updated when needed. For questions about an order, course or account, contact the center through the Contact page.</p>
            </div>
        @elseif ($policy === 'privacy')
            <div class="prose prose-slate mt-8 max-w-none">
                <h2>Information we use</h2><p>The platform may process information you provide, such as your name, contact details, enrolment information, learning activity, submitted work and payment references, to operate the learning service.</p>
                <h2>How information is used</h2><p>Information is used to manage accounts, deliver courses, support teachers and students, verify payments, provide notices and improve platform operations.</p>
                <h2>Security and service providers</h2><p>Reasonable technical and organizational safeguards are used to protect platform information. Payment processing may involve the selected payment provider, which handles payment data under its own terms.</p>
                <h2>Optional cookies and analytics</h2><p>If configured, Google Analytics 4, tags configured through Google Tag Manager and Meta Pixel are loaded only after you choose to allow the relevant optional category in the cookie notice. Google Tag Manager uses Google Consent Mode signals so configured tags can respect your separate Analytics and Marketing choices. Analytics and Marketing choices are separate; rejecting optional cookies does not affect essential website functions. We do not load these optional trackers before consent.</p>
                <h2>Changing your choice</h2><p>You can reopen Cookie settings from the website footer to change or withdraw optional consent. A tracker that has already loaded may have placed third-party cookies; you may also need to remove those cookies using your browser settings. Google and Meta process information under their own privacy terms.</p>
                <h2>Your questions</h2><p>For questions or requests concerning your account information, contact the center using the details on the Contact page.</p>
            </div>
        @else
            <div class="prose prose-slate mt-8 max-w-none">
                <h2>Refund requests</h2><p>Refund eligibility depends on the course, service, purchase details and any conditions shown at the time of purchase or confirmed by the center. Please review the applicable course and checkout information before paying.</p>
                <h2>How to request a review</h2><p>Contact the center with your order number, payment reference, course name and reason for the request. The administration team will review the order and payment ledger before making a decision.</p>
                <h2>Approved refunds</h2><p>Where a refund is approved, the center will confirm the amount and the available return method. Processing time may depend on the payment provider or financial institution.</p>
                <h2>Need help?</h2><p>Use the Contact page for assistance with a payment, order or refund request.</p>
            </div>
        @endif
        <a href="{{ route('contact') }}" class="mt-8 inline-flex rounded-full bg-cyan-600 px-5 py-3 text-sm font-bold text-white hover:bg-teal-700">Contact us</a>
    </div>
</section>
@endsection
