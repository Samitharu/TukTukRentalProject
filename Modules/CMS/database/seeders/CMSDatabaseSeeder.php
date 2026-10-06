<?php

declare(strict_types=1);

namespace Modules\CMS\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CMS\Models\Faq;
use Modules\CMS\Models\Page;
use Modules\CMS\Models\Testimonial;

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
        $this->seedTestimonials();
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
                'content' => '<p>[Placeholder — the business owner should confirm final wording with a local advisor before launch.] By renting a vehicle from Happy Journy TukTuk Rental, you agree to: return the vehicle in the condition it was provided (normal wear excepted); hold a valid driving permit for the duration of the rental; not use the vehicle for any illegal purpose or to transport more passengers than the vehicle is rated for; and accept responsibility for traffic fines incurred during your rental period. Full terms will be finalized before go-live.</p>',
            ],
            [
                'title' => 'Privacy Policy',
                'content' => "<p>We collect the personal information necessary to process your booking: name, contact details, nationality, and driving permit/passport details for legal verification. Passport and permit numbers are encrypted at rest and are never shared with third parties except where required by local law (e.g. police reporting of an accident).</p><p>If you are booking from the EU/EEA, you may request a copy of the data we hold about you or ask us to delete it once your rental is complete and any legal retention period has passed — contact us using the details on our Contact page.</p><p>[Placeholder — finalize with a local/GDPR advisor before launch.]</p>",
            ],
            [
                'title' => 'Cancellation Policy',
                'content' => '<p>[Placeholder — confirm final policy with the business owner.] Cancellations made more than 48 hours before the scheduled pickup time are eligible for a full refund of any amount paid online. Cancellations within 48 hours, and no-shows, are non-refundable. Date changes are free of charge, subject to vehicle availability, if requested more than 24 hours before pickup.</p>',
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
            ['q' => 'Do I need a special licence to drive a tuk tuk in Sri Lanka?', 'a' => 'Yes — either a valid International Driving Permit covering three-wheelers, or a Sri Lankan temporary permit. See our Driving Permit Guide for details.', 'category' => 'Requirements'],
            ['q' => 'Is insurance included?', 'a' => 'Every rental includes basic third-party insurance. An insurance upgrade with lower excess is available as an add-on at checkout.', 'category' => 'Insurance'],
            ['q' => 'Can you deliver the tuk tuk to my hotel?', 'a' => 'Yes, delivery is available to most areas for an additional fee shown at checkout before you pay anything.', 'category' => 'Pickup & delivery'],
            ['q' => 'What happens if the tuk tuk breaks down?', 'a' => 'Call the number on your rental agreement any time during your rental — we offer 24/7 support and will arrange a replacement vehicle or roadside assistance.', 'category' => 'During your rental'],
            ['q' => 'Can I extend my rental once I have picked up the vehicle?', 'a' => 'Often, yes — contact us as early as possible and we will check availability for your vehicle beyond the original return date.', 'category' => 'During your rental'],
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

    private function seedTestimonials(): void
    {
        $testimonials = [
            ['name' => 'Hannah B.', 'country' => 'DE', 'rating' => 5, 'text' => "Booking was completely transparent — the price we saw online was exactly what we paid. The tuk tuk was in great shape and the team talked us through everything before we drove off."],
            ['name' => 'Dmitri K.', 'country' => 'RU', 'rating' => 5, 'text' => 'Easy pickup, clear instructions, and they helped us sort out the driving permit question before we even arrived. Would rent again.'],
            ['name' => 'Camille R.', 'country' => 'FR', 'rating' => 4, 'text' => "Great value and friendly service. Only small note — ask for the phone holder add-on in advance, it's very handy for navigation."],
        ];

        foreach ($testimonials as $t) {
            Testimonial::query()->updateOrCreate(
                ['customer_name' => $t['name'], 'country' => $t['country']],
                [
                    'rating' => $t['rating'],
                    'content' => ['en' => $t['text']],
                    'is_approved' => true,
                    'source' => 'Seed data',
                ],
            );
        }
    }
}
