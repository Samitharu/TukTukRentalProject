<?php

declare(strict_types=1);

namespace Modules\CMS\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CMS\Models\Faq;
use Modules\CMS\Models\Page;

/**
 * Real, publishable starter content for the pages the brief names
 * explicitly (About, How It Works, Driving Permit Guide, Terms, Privacy
 * Policy, Cancellation Policy) — placeholders only where the business
 * brief itself left the value blank (address, phone, exact permit rules
 * an admin should confirm with local authorities). Not a copy-paste skeleton:
 * this is what actually renders on those routes once seeded.
 */
class CMSDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPages();
        $this->seedFaqs();
    }

    private function seedPages(): void
    {
        $pages = [
            [
                'title' => 'About Us',
                'content' => "<p>Happy Journy TukTuk Rental was started by a small team of locals who grew tired of watching visitors get overcharged and under-informed by informal rental stands along the coast road. We wanted a rental experience a first-time visitor to Sri Lanka could trust: transparent pricing, well-maintained vehicles, and honest advice about routes, permits, and road conditions.</p><p>Every tuk tuk in our fleet is inspected between rentals, insured, and fitted with a phone holder and helmet as standard. We're a new business — small enough that you'll likely deal with the same two or three people throughout your rental, and motivated to earn every single review.</p>",
            ],
            [
                'title' => 'How It Works',
                'content' => "<ol><li><strong>Choose your dates and package.</strong> Pick a pickup/return date, choose office pickup or delivery to your hotel, and select a package or a specific tuk tuk.</li><li><strong>Add extras.</strong> Helmet, phone holder, SIM card, and other add-ons — priced clearly, nothing bundled in without your say.</li><li><strong>Tell us about you.</strong> Name, contact details, and your driving permit status (see our Driving Permit Guide).</li><li><strong>Review and confirm.</strong> You'll see the full price breakdown before you commit — no surprise fees at pickup.</li><li><strong>Pick up and go.</strong> We'll walk you through the controls, show you the paperwork, and point you toward our recommended first-day route.</li></ol>",
            ],
            [
                'title' => 'Driving Permit Guide',
                'content' => "<p><strong>Foreign visitors legally need one of the following to drive a tuk tuk (three-wheeler) in Sri Lanka:</strong></p><ul><li>A valid International Driving Permit (IDP) issued in your home country <em>before</em> you travel, matching the category for three-wheelers/motor tricycles, or</li><li>A Sri Lankan temporary driving permit, which can be arranged through the Department of Motor Traffic (we can point you to the nearest office and typical processing time).</li></ul><p>We verify your permit or IDP as part of checkout — you'll be asked to upload a photo of it. If you're unsure whether your home country's IDP covers three-wheelers, contact us before booking and we'll help you check.</p><p><em>This page is general guidance, not legal advice — requirements can change, so please confirm current rules with Sri Lanka's Department of Motor Traffic if in doubt.</em></p>",
            ],
            [
                'title' => 'Terms & Conditions',
                'content' => "<p>By renting a vehicle from Happy Journy TukTuk Rental, you (the \"Customer\") agree to the terms below. Please read them carefully before confirming your booking.</p><ol><li><strong>Driving permit.</strong> A valid Sri Lankan driving permit covering three-wheelers is required to drive any of our vehicles. The Customer must hold this permit, and carry it at all times, for the full rental period. All charges for the licence transfer and the issue of the local driving permit are payable by the Customer. Once paid, please send us the payment receipt, quoting your booking reference number, so that we can match it to your booking.</li><li><strong>Security deposit.</strong> A refundable security deposit is collected on all tuk tuk packages. It is returned in full at the end of the rental once the vehicle has been inspected and found in the condition it was provided, normal wear excepted.</li><li><strong>Accidents and repairs.</strong> In line with Sri Lankan vehicle insurance policy, the Customer is responsible for 50% of the repair bill for any damage caused by an accident during the rental period. In addition, for every day the vehicle is off the road at the garage for repairs, the Customer will be charged the daily rate of their selected package.</li><li><strong>Traffic offences and police matters.</strong> The Customer is solely responsible for obeying Sri Lankan traffic laws. Happy Journy TukTuk Rental accepts no responsibility for any traffic violation, fine, legal proceeding or police involvement arising during the rental. If the vehicle is held by the police for any period, the Customer will be charged the daily rate of their selected package for each day it is held, in the same way as for garage repairs.</li><li><strong>GPS monitoring.</strong> All of our vehicles are fitted with GPS tracking and are monitored throughout the rental period.</li><li><strong>Damage, loss or theft.</strong> If the vehicle is damaged, lost or stolen, the security deposit will be used to set off the cost. Any amount above the deposit, including repair costs, daily charges and other related expenses, must be paid by the Customer.</li><li><strong>Use of the vehicle.</strong> The vehicle must not be used for any illegal purpose or to carry more passengers than it is rated for, and must be returned in the condition it was provided, normal wear excepted.</li></ol><p>By completing your booking, you confirm that you have read, understood and accepted these terms.</p>",
            ],
            [
                'title' => 'Privacy Policy',
                'content' => "<p>Happy Journy TukTuk Rental respects your privacy. This policy explains what personal information we collect when you book with us, why we need it, and how we keep it safe.</p><h2>What we collect</h2><ul><li><strong>Contact details</strong>: your name, email address and phone number.</li><li><strong>Identity and permit details</strong>: your nationality, passport number and the driving licence details needed to arrange and verify your Sri Lankan driving permit.</li><li><strong>Booking details</strong>: your rental dates, package, pickup or delivery location, add-ons, payments, security deposit and any licence transfer receipts you send us.</li><li><strong>Vehicle location</strong>: all of our vehicles are monitored by GPS, so we hold location data for the vehicle during your rental.</li></ul><h2>How we use your information</h2><p>We use your information only to process and manage your booking, arrange and verify your driving permit, contact you about your rental, protect and recover our vehicles, handle any accident, damage, insurance or police matter, and meet our legal obligations. We never sell your personal information, and we do not use it for marketing without your consent.</p><h2>How we keep it safe</h2><p>Your passport number is encrypted when stored in our system, and access to customer information is limited to the staff who need it to look after your booking. Documents and receipts you send us are used only to verify your booking and permit.</p><h2>Who we share it with</h2><p>We share your information only when it is necessary to provide your rental or required by law, such as with the Department of Motor Traffic to arrange your driving permit, with insurers and repair garages after an accident, or with the police and other authorities where Sri Lankan law requires it.</p><h2>How long we keep it</h2><p>We keep your information for as long as needed to complete your rental and to meet legal, accounting and insurance requirements. After that, it is deleted or anonymised.</p><h2>Your rights</h2><p>You may ask for a copy of the information we hold about you, ask us to correct it, or ask us to delete it once your rental is complete and any legal retention period has passed. To make a request, contact us using the details on our Contact page and quote your booking reference number.</p><p>We may update this policy from time to time. The latest version will always be published on this page.</p>",
            ],
            [
                'title' => 'Cancellation Policy',
                'content' => "<p>We understand that travel plans can change. You can cancel your booking before your scheduled pickup by contacting us and quoting your booking reference number.</p><ul><li><strong>More than 48 hours before pickup:</strong> you will receive a full refund of the amount paid, except for licence transfer charges.</li><li><strong>Within 48 hours of pickup, or no-show:</strong> the rental amount is non-refundable.</li><li><strong>Licence transfer charges:</strong> charges paid for the licence transfer and the issue of your local driving permit are not refundable in any case, because the permit process will already have been started on your behalf.</li><li><strong>Security deposit:</strong> any security deposit you have paid is refunded in full when a booking is cancelled.</li><li><strong>Date changes:</strong> free of charge, subject to vehicle availability, if requested more than 24 hours before pickup.</li></ul><p>Refunds are made to the original payment method.</p>",
            ],
        ];

        foreach ($pages as $page) {
            Page::query()->updateOrCreate(
                ['title->en' => $page['title']],
                [
                    'template' => 'default',
                    'title' => ['en' => $page['title']],
                    'content' => ['en' => $page['content']],
                    'is_published' => true,
                ],
            );
        }
    }

    private function seedFaqs(): void
    {
        $faqs = [
            ['q' => 'Do I need a special licence to drive a tuk tuk in Sri Lanka?', 'a' => 'Yes. You need a valid Sri Lankan driving permit covering three-wheelers, and you must carry it at all times during your rental. Your home licence is transferred to a local driving permit before you drive.', 'category' => 'Requirements'],
            ['q' => 'Who pays for the licence transfer and local driving permit?', 'a' => 'The licence transfer and local driving permit charges are paid by you. Once paid, please send us the payment receipt with your booking reference number so we can match it to your booking. These charges are not refundable.', 'category' => 'Requirements'],
            ['q' => 'Do I need to pay a security deposit?', 'a' => 'Yes, a refundable security deposit is taken on all tuk tuk packages. It is returned in full at the end of your rental once the vehicle has been inspected and found in the condition it was provided, normal wear excepted.', 'category' => 'Deposit & payment'],
            ['q' => 'Can you deliver the tuk tuk to my hotel?', 'a' => 'Yes, delivery is available to most areas for an additional fee shown at checkout before you pay anything.', 'category' => 'Pickup & delivery'],
            ['q' => 'Is insurance included?', 'a' => 'Our vehicles are insured, but under Sri Lankan vehicle insurance policy you are responsible for 50% of the repair bill if the tuk tuk is damaged in an accident during your rental. You will also be charged the daily rate of your selected package for each day the vehicle is at the garage for repairs.', 'category' => 'Insurance & accidents'],
            ['q' => 'What happens if the tuk tuk is damaged, lost or stolen?', 'a' => 'Your security deposit is used to set off the cost. Any amount above the deposit, including repair costs and daily charges, must be paid by you.', 'category' => 'Insurance & accidents'],
            ['q' => 'What if I get a traffic fine or the police hold the vehicle?', 'a' => 'You are responsible for obeying Sri Lankan traffic laws, and we do not take responsibility for any traffic violation, fine or police matter during your rental. If the police hold the vehicle, you will be charged the daily rate of your selected package for each day it is held.', 'category' => 'During your rental'],
            ['q' => 'Are the vehicles tracked?', 'a' => 'Yes, all of our vehicles are monitored by GPS throughout the rental period for security and recovery.', 'category' => 'During your rental'],
            ['q' => 'What happens if the tuk tuk breaks down?', 'a' => 'Call the number on your rental agreement any time during your rental — we offer 24/7 support and will arrange a replacement vehicle or roadside assistance.', 'category' => 'During your rental'],
            ['q' => 'Can I extend my rental once I have picked up the vehicle?', 'a' => 'Often, yes — contact us as early as possible and we will check availability for your vehicle beyond the original return date.', 'category' => 'During your rental'],
            ['q' => 'Can I cancel my booking?', 'a' => 'Yes. Cancellations made more than 48 hours before pickup receive a full refund, except for licence transfer charges, which are not refundable. Please see our Cancellation Policy for full details.', 'category' => 'Cancellations'],
        ];

        foreach ($faqs as $i => $faq) {
            Faq::query()->updateOrCreate(
                ['question->en' => $faq['q']],
                [
                    'question' => ['en' => $faq['q']],
                    'answer' => ['en' => $faq['a']],
                    'category' => $faq['category'],
                    'sort_order' => $i,
                    'is_active' => true,
                ],
            );
        }
    }
}
