<?php

declare(strict_types=1);

namespace Database;

use App\Cms\Blocks;
use App\Cms\Design;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Migrator;
use App\Core\Permissions;
use App\Services\EmailTemplates;

/**
 * Idempotent seed: running it twice is safe.
 *
 *  - permission catalogue and the four built-in roles
 *  - India (default, served from /) and the UAE (/ae)
 *  - the first administrator, asked for in the terminal when there is none yet
 *  - email templates
 *  - demo content when the site is empty (skip with --no-demo)
 */
final class Seeder
{
    /** @var callable(string): void */
    private $out;

    /** @var (callable(string, bool): string)|null */
    private $ask;

    /** @param (callable(string, bool): string)|null $ask prompts for the first admin; null skips it */
    public function __construct(callable $out, ?callable $ask = null, private readonly bool $demo = true)
    {
        $this->out = $out;
        $this->ask = $ask;
    }

    public function run(): void
    {
        Migrator::migrate();
        Permissions::sync();
        ($this->out)('Permissions and roles synced.');

        $this->countries();
        $this->admin();
        EmailTemplates::seed();
        ($this->out)('Email templates ready.');

        if ($this->demo && (int) DB::value('SELECT COUNT(*) FROM pages') === 0) {
            $this->demo();
            ($this->out)('Demo content created.');
        }
    }

    private function countries(): void
    {
        DB::query(
            "INSERT IGNORE INTO countries (name, code, slug, locale, currency, currency_symbol, phone_code, timezone, is_default, is_active, is_published, sort_order, settings)
             VALUES ('India', 'IN', '', 'en-IN', 'INR', '₹', '+91', 'Asia/Kolkata', 1, 1, 1, 0, ?),
                    ('United Arab Emirates', 'AE', 'ae', 'en-AE', 'AED', 'AED ', '+971', 'Asia/Dubai', 0, 1, 1, 1, ?)",
            [
                json_encode(['company_name' => 'Dropbox Reseller India', 'sales_phone' => '+91 98765 43210', 'sales_email' => 'sales@example.com', 'whatsapp' => '+919876543210', 'address' => 'Connaught Place, New Delhi 110001', 'business_hours' => 'Mon–Sat, 9:30–18:30 IST', 'tax_label' => 'GSTIN']),
                json_encode(['company_name' => 'Dropbox Reseller UAE', 'sales_phone' => '+971 4 000 0000', 'sales_email' => 'uae@example.com', 'address' => 'Business Bay, Dubai', 'business_hours' => 'Sun–Thu, 9:00–18:00 GST', 'tax_label' => 'TRN']),
            ]
        );
        ($this->out)('Markets: India (/) and UAE (/ae).');
    }

    private function admin(): void
    {
        $roleId = (int) DB::value("SELECT id FROM roles WHERE slug = 'super-admin'");
        if (DB::value('SELECT COUNT(*) FROM users WHERE role_id = ? AND deleted_at IS NULL', [$roleId])) {
            ($this->out)('Administrator already exists.');
            return;
        }
        if ($this->ask === null) {
            ($this->out)('No administrator yet. Run: php bin/console user:create');
            return;
        }
        ($this->out)('Create the first administrator:');
        for ($try = 0; $try < 3; $try++) {
            $email = strtolower(trim(($this->ask)('  Email: ', false)));
            $name = trim(($this->ask)('  Name: ', false)) ?: 'Administrator';
            $password = ($this->ask)('  Password (10+ chars, upper + lower case and a number): ', true);
            $error = filter_var($email, FILTER_VALIDATE_EMAIL) ? Auth::passwordError($password, $email) : 'Enter a valid email address.';
            if ($error === null) {
                DB::insert('users', [
                    'email' => $email, 'name' => $name, 'password_hash' => Auth::hash($password),
                    'role_id' => $roleId, 'status' => 'ACTIVE', 'password_changed_at' => now(),
                ]);
                ($this->out)("Administrator $email created. Sign in at " . config('app.url') . config('app.login_path'));
                return;
            }
            ($this->out)('  ' . $error);
        }
        ($this->out)('No administrator created. Run: php bin/console user:create');
    }

    // --- demo content ----------------------------------------------------------

    private function demo(): void
    {
        DB::transaction(function (): void {
            $in = (int) DB::value("SELECT id FROM countries WHERE code = 'IN'");
            $ae = (int) DB::value("SELECT id FROM countries WHERE code = 'AE'");
            $forms = $this->forms();
            $products = $this->products($in, $ae, $forms['quote']);
            $pages = $this->pages($in, $ae);
            $this->navigation($in, $pages[$in]);
            $this->navigation($ae, $pages[$ae]);
            $this->blog($in);
            $this->leads($in, $products, $forms['contact']);
        });
    }

    /** @return array<string,int> */
    private function forms(): array
    {
        $ids = [];
        $defs = [
            'contact' => ['Contact', 'Send message', 'Thanks — our team will reply within one business day.', [
                ['NAME', 'Full name', 'name', true, 'half'], ['EMAIL', 'Work email', 'email', true, 'half'],
                ['PHONE', 'Phone', 'phone', false, 'half'], ['COMPANY', 'Company', 'company', false, 'half'],
                ['TEXTAREA', 'How can we help?', 'message', false, 'full'],
            ]],
            'quote' => ['Get a quote', 'Request pricing', 'Thank you. We will send your quote shortly.', [
                ['NAME', 'Full name', 'name', true, 'full'], ['EMAIL', 'Work email', 'email', true, 'full'],
                ['PHONE', 'Phone', 'phone', true, 'full'], ['COMPANY', 'Company', 'company', false, 'full'],
                ['SELECT', 'Number of users', 'users', false, 'full', ['1–5', '6–20', '21–100', '100+']],
            ]],
            'newsletter' => ['Newsletter', 'Subscribe', 'You are subscribed. Watch your inbox.', [
                ['EMAIL', 'Email', 'email', true, 'full'],
            ]],
        ];
        foreach ($defs as $slug => [$name, $submit, $success, $fields]) {
            $id = DB::insert('forms', [
                'name' => $name, 'slug' => $slug, 'submit_label' => $submit, 'success_message' => $success,
                'lead_source' => 'Website Form', 'creates_lead' => 1, 'offer_marketing_consent' => $slug !== 'newsletter' ? 1 : 0,
                'consent_text' => 'I agree to be contacted about my enquiry.',
            ]);
            foreach ($fields as $i => $f) {
                DB::insert('form_fields', [
                    'form_id' => $id, 'type' => $f[0], 'label' => $f[1], 'name' => $f[2], 'is_required' => $f[3] ? 1 : 0,
                    'width' => $f[4], 'options' => isset($f[5]) ? $f[5] : null, 'sort_order' => $i * 10,
                ]);
            }
            $ids[$slug] = $id;
        }
        return $ids;
    }

    /** @return list<int> */
    private function products(int $in, int $ae, int $quoteForm): array
    {
        $cat = DB::insert('product_categories', ['name' => 'Dropbox for teams', 'slug' => 'dropbox-teams']);
        $catP = DB::insert('product_categories', ['name' => 'Dropbox for individuals', 'slug' => 'dropbox-individuals', 'sort_order' => 1]);
        $brand = DB::insert('brands', ['name' => 'Dropbox', 'slug' => 'dropbox', 'website_url' => 'https://www.dropbox.com']);
        $plans = [
            ['Dropbox Plus', 'dropbox-plus', $catP, '2 TB', 1, 1, 999, 833, false, 'For one person who needs space and simple sharing.',
                ['2 TB of storage', 'Use on unlimited devices', '30-day file recovery', 'Transfer files up to 50 GB'], 'per month'],
            ['Dropbox Essentials', 'dropbox-essentials', $catP, '3 TB', 1, 1, 1650, 1375, false, 'For professionals who want to work and share with clients.',
                ['3 TB of storage', '180-day file recovery', 'Branded sharing', 'Transfer files up to 100 GB'], 'per month'],
            ['Dropbox Business', 'dropbox-business', $cat, '9 TB for the team', 3, null, 1250, 1050, true, 'For small teams that need to collaborate securely.',
                ['Starts at 9 TB for the team', 'Admin console and user management', '180-day file recovery', 'Transfer files up to 100 GB', 'Unlimited apps integrations'], 'per user / month'],
            ['Dropbox Business Plus', 'dropbox-business-plus', $cat, '15 TB for the team', 3, null, 2050, 1700, true, 'For growing teams that need advanced controls.',
                ['Starts at 15 TB for the team', '1-year file recovery and version history', 'Tiered admin roles', 'Ransomware detection and recovery', 'Transfer files up to 250 GB'], 'per user / month'],
            ['Dropbox Enterprise', 'dropbox-enterprise', $cat, 'Custom', 100, null, null, null, false, 'For large organisations with compliance needs.',
                ['As much space as you need', 'Enterprise login and SSO', 'Advanced security and compliance', 'Dedicated support'], 'contact us'],
        ];
        $ids = [];
        foreach ($plans as $i => [$name, $slug, $catId, $storage, $min, $max, $monthly, $annual, $featured, $short, $features, $suffix]) {
            $id = DB::insert('products', [
                'name' => $name, 'slug' => $slug, 'status' => 'PUBLISHED', 'published_at' => now(), 'is_featured' => $featured ? 1 : 0,
                'sort_order' => $i * 10, 'featured_order' => $i, 'short_description' => $short,
                'description' => '<p>' . $short . ' Buy through an authorised partner for local billing, deployment help and support.</p>',
                'storage' => $storage, 'storage_label' => 'Storage', 'min_users' => $min, 'max_users' => $max, 'users_label' => 'Users',
                'currency' => 'INR', 'monthly_price' => $monthly, 'annual_price' => $annual, 'price_suffix' => $suffix,
                'features' => $features, 'benefits' => ['Genuine licences from an authorised partner', 'Local currency invoicing', 'Free onboarding call'],
                'specs' => [['label' => 'Storage', 'value' => $storage], ['label' => 'Users', 'value' => $min . ($max ? '–' . $max : '+')], ['label' => 'File recovery', 'value' => $i >= 3 ? '1 year' : ($i >= 1 ? '180 days' : '30 days')]],
                'cta_label' => $monthly ? 'Get started' : 'Contact sales', 'cta_form_id' => $quoteForm,
                'category_id' => $catId, 'brand_id' => $brand,
            ]);
            foreach ([[$in, 'INR', $monthly, $annual], [$ae, 'AED', $monthly ? round($monthly / 22) : null, $annual ? round($annual / 22) : null]] as [$cid, $cur, $m, $a]) {
                DB::insert('product_countries', [
                    'product_id' => $id, 'country_id' => $cid, 'status' => 'PUBLISHED', 'published_at' => now(),
                    'is_featured' => $featured ? 1 : 0, 'sort_order' => $i * 10, 'featured_order' => $i,
                    'currency' => $cur, 'monthly_price' => $m, 'annual_price' => $a, 'price_suffix' => $suffix,
                ]);
            }
            $ids[] = $id;
        }
        return $ids;
    }

    /** @return array<int, array<string,int>> pages per market by slug */
    private function pages(int $in, int $ae): array
    {
        $result = [];
        foreach ([$in => 'India', $ae => 'the UAE'] as $cid => $where) {
            $home = $this->page($cid, 'Home', '', [
                ['hero', ['layout' => 'contentForm', 'eyebrow' => 'Authorised Dropbox Partner in ' . $where, 'heading' => 'Buy Dropbox for your team, with local billing and support',
                    'description' => 'Genuine Dropbox Business licences, deployed by experts. Get a quote in minutes.',
                    'bullets' => ['Local currency invoicing', 'Free migration planning', 'Support from real people'],
                    'primary_label' => 'See plans', 'primary_url' => '/pricing', 'secondary_label' => 'Talk to sales', 'secondary_url' => '/contact',
                    'form_slug' => 'quote', 'form_heading' => 'Get a quote', 'badges' => [['icon' => 'shield-check', 'label' => 'Authorised partner'], ['icon' => 'award', 'label' => '4.9/5 rated']]], ['preset' => 'gradient']],
                ['logoWall', ['heading' => 'Trusted by teams across ' . $where], []],
                ['featureGrid', [], []],
                ['productGrid', ['heading' => 'Plans for every team', 'source' => 'featured', 'billing' => 'toggle'], ['preset' => 'muted']],
                ['stats', [], []],
                ['steps', [], ['preset' => 'muted']],
                ['comparisonTable', [], []],
                ['testimonials', [], []],
                ['faq', [], ['preset' => 'muted']],
                ['cta', ['primary_label' => 'Get a quote', 'primary_url' => '/contact', 'secondary_label' => 'Compare plans', 'secondary_url' => '/pricing'], ['preset' => 'brand']],
            ]);
            $pricing = $this->page($cid, 'Pricing', 'pricing', [
                ['headingText', ['heading' => 'Dropbox pricing', 'body' => '<p>Simple plans, billed in your currency. Annual billing saves up to 20%.</p>'], []],
                ['productGrid', ['heading' => '', 'source' => 'all', 'billing' => 'toggle', 'limit' => 6], []],
                ['productTable', ['heading' => 'Compare every plan', 'source' => 'all', 'rows' => ['Storage', 'Users', 'File recovery']], ['preset' => 'muted']],
                ['faq', [], []],
            ]);
            $contact = $this->page($cid, 'Contact', 'contact', [
                ['formBlock', ['heading' => 'Talk to our Dropbox team', 'form_slug' => 'contact', 'bullets' => ['Pricing and quotes', 'Migration and deployment', 'Renewals and upgrades']], []],
                ['contactDetails', [], ['preset' => 'muted']],
            ]);
            $about = $this->page($cid, 'About us', 'about', [
                ['headingText', ['heading' => 'About us', 'body' => '<p>We help organisations buy, deploy and get the most from Dropbox.</p>'], []],
                ['imageContent', ['heading' => 'Partners, not just resellers', 'bullets' => ['Certified Dropbox specialists', 'Hundreds of successful deployments', 'Local support team']], []],
            ]);
            $privacy = $this->page($cid, 'Privacy policy', 'privacy-policy', [
                ['richText', ['body' => '<h1>Privacy policy</h1><p>This policy explains what personal data we collect, why, and your rights. Replace this text with your own policy.</p>', 'max_width' => 'narrow'], []],
            ]);
            $terms = $this->page($cid, 'Terms of service', 'terms', [
                ['richText', ['body' => '<h1>Terms of service</h1><p>Replace this text with your terms.</p>', 'max_width' => 'narrow'], []],
            ]);
            $result[$cid] = ['' => $home, 'pricing' => $pricing, 'contact' => $contact, 'about' => $about, 'privacy-policy' => $privacy, 'terms' => $terms];
        }
        return $result;
    }

    /** @param list<array{0:string,1:array<string,mixed>,2:array<string,mixed>}> $sections */
    private function page(int $countryId, string $title, string $slug, array $sections): int
    {
        $id = DB::insert('pages', [
            'country_id' => $countryId, 'title' => $title, 'slug' => $slug, 'status' => 'PUBLISHED',
            'published_at' => now(), 'is_homepage' => $slug === '' ? 1 : 0,
        ]);
        foreach ($sections as $i => [$type, $content, $design]) {
            DB::insert('page_sections', [
                'page_id' => $id, 'block_type' => $type, 'sort_order' => $i * 10, 'is_visible' => 1,
                'content' => Blocks::normalize($type, $content + Blocks::defaults($type)),
                'settings' => Design::normalize($design),
            ]);
        }
        return $id;
    }

    /** @param array<string,int> $pages */
    private function navigation(int $countryId, array $pages): void
    {
        $header = DB::insert('navigations', ['country_id' => $countryId, 'name' => 'Main menu', 'location' => 'HEADER']);
        $plans = DB::insert('navigation_items', ['navigation_id' => $header, 'label' => 'Plans', 'link_type' => 'PAGE', 'page_id' => $pages['pricing'], 'sort_order' => 0, 'mega_menu' => 0]);
        $products = DB::all('SELECT id, name, short_description FROM products ORDER BY sort_order');
        foreach ($products as $i => $p) {
            DB::insert('navigation_items', ['navigation_id' => $header, 'parent_id' => $plans, 'label' => $p['name'], 'link_type' => 'PRODUCT', 'product_id' => $p['id'], 'description' => mb_substr((string) $p['short_description'], 0, 120), 'icon' => 'dropbox', 'sort_order' => $i]);
        }
        DB::insert('navigation_items', ['navigation_id' => $header, 'label' => 'Blog', 'link_type' => 'INTERNAL', 'url' => '/blog', 'sort_order' => 1]);
        DB::insert('navigation_items', ['navigation_id' => $header, 'label' => 'About', 'link_type' => 'PAGE', 'page_id' => $pages['about'], 'sort_order' => 2]);
        DB::insert('navigation_items', ['navigation_id' => $header, 'label' => 'Contact', 'link_type' => 'PAGE', 'page_id' => $pages['contact'], 'sort_order' => 3]);

        $footer = DB::insert('navigations', ['country_id' => $countryId, 'name' => 'Footer', 'location' => 'FOOTER']);
        $col1 = DB::insert('navigation_items', ['navigation_id' => $footer, 'label' => 'Products', 'link_type' => 'INTERNAL', 'url' => '/pricing', 'sort_order' => 0]);
        foreach (array_slice($products, 0, 4) as $i => $p) {
            DB::insert('navigation_items', ['navigation_id' => $footer, 'parent_id' => $col1, 'label' => $p['name'], 'link_type' => 'PRODUCT', 'product_id' => $p['id'], 'sort_order' => $i]);
        }
        $col2 = DB::insert('navigation_items', ['navigation_id' => $footer, 'label' => 'Company', 'link_type' => 'INTERNAL', 'url' => '/about', 'sort_order' => 1]);
        DB::insert('navigation_items', ['navigation_id' => $footer, 'parent_id' => $col2, 'label' => 'About us', 'link_type' => 'PAGE', 'page_id' => $pages['about'], 'sort_order' => 0]);
        DB::insert('navigation_items', ['navigation_id' => $footer, 'parent_id' => $col2, 'label' => 'Blog', 'link_type' => 'INTERNAL', 'url' => '/blog', 'sort_order' => 1]);
        DB::insert('navigation_items', ['navigation_id' => $footer, 'parent_id' => $col2, 'label' => 'Contact', 'link_type' => 'PAGE', 'page_id' => $pages['contact'], 'sort_order' => 2]);

        $legal = DB::insert('navigations', ['country_id' => $countryId, 'name' => 'Legal', 'location' => 'LEGAL']);
        DB::insert('navigation_items', ['navigation_id' => $legal, 'label' => 'Privacy policy', 'link_type' => 'PAGE', 'page_id' => $pages['privacy-policy'], 'sort_order' => 0]);
        DB::insert('navigation_items', ['navigation_id' => $legal, 'label' => 'Terms', 'link_type' => 'PAGE', 'page_id' => $pages['terms'], 'sort_order' => 1]);
    }

    private function blog(int $countryId): void
    {
        $guides = DB::insert('blog_categories', ['name' => 'Guides', 'slug' => 'guides', 'description' => 'How-tos for Dropbox admins and teams.']);
        $news = DB::insert('blog_categories', ['name' => 'News', 'slug' => 'news', 'sort_order' => 1]);
        $tagSec = DB::insert('blog_tags', ['name' => 'Security', 'slug' => 'security']);
        $tagMig = DB::insert('blog_tags', ['name' => 'Migration', 'slug' => 'migration']);
        $author = DB::value('SELECT id FROM users ORDER BY id LIMIT 1');
        $posts = [
            ['How to choose the right Dropbox plan for your team', 'choose-dropbox-plan', $guides, [$tagMig],
                '<h2>Start with storage and users</h2><p>Count the people who need access and estimate how much data you will move.</p><h2>Think about recovery</h2><p>Longer version history protects you from mistakes and ransomware.</p><h3>Business vs Business Plus</h3><p>Business Plus adds a year of file recovery and advanced admin roles.</p><h2>Talk to a partner</h2><p>An authorised partner can quote in your currency and plan the migration.</p>'],
            ['Five Dropbox security settings every admin should turn on', 'dropbox-security-settings', $guides, [$tagSec],
                '<h2>1. Two-step verification</h2><p>Require it for every member.</p><h2>2. Device approvals</h2><p>Limit how many devices each user can link.</p><h2>3. Sharing controls</h2><p>Decide who can share outside the team.</p><h2>4. Ransomware detection</h2><p>Get alerts and roll back in one click.</p><h2>5. Activity reports</h2><p>Review sign-ins and sharing regularly.</p>'],
            ['Migrating from Google Drive to Dropbox without downtime', 'google-drive-to-dropbox', $news, [$tagMig],
                '<h2>Plan the cut-over</h2><p>Migrate in waves, team by team.</p><h2>Keep permissions</h2><p>Map shared drives to team folders before you move a single file.</p>'],
        ];
        foreach ($posts as $i => [$title, $slug, $cat, $tags, $content]) {
            $id = DB::insert('blog_posts', [
                'country_id' => $countryId, 'title' => $title, 'slug' => $slug, 'status' => 'PUBLISHED',
                'published_at' => date('Y-m-d H:i:s', time() - ($i + 1) * 86400 * 3), 'excerpt' => strip_tags(explode('</p>', $content)[0]),
                'content' => $content, 'reading_time' => max(1, (int) ceil(str_word_count(strip_tags($content)) / 220)),
                'category_id' => $cat, 'author_id' => $author, 'is_featured' => $i === 0 ? 1 : 0,
            ]);
            foreach ($tags as $t) {
                DB::insert('blog_post_tags', ['post_id' => $id, 'tag_id' => $t]);
            }
        }
    }

    /** @param list<int> $products */
    private function leads(int $countryId, array $products, int $formId): void
    {
        $samples = [
            ['Ananya Rao', 'ananya@example.com', 'Northwind Pvt Ltd', 'NEW', 'google', 'cpc', 'dropbox-brand'],
            ['Rahul Mehta', 'rahul@example.com', 'Acme Studios', 'CONTACTED', 'linkedin', 'social', 'q3-teams'],
            ['Fatima Khan', 'fatima@example.com', 'Blue Ocean Traders', 'QUALIFIED', 'google', 'organic', ''],
            ['Vikram Singh', 'vikram@example.com', 'Delta Engineering', 'PROPOSAL', 'newsletter', 'email', 'sept-offer'],
            ['Neha Gupta', 'neha@example.com', 'Sunrise Clinics', 'WON', '(direct)', '(none)', ''],
        ];
        foreach ($samples as $i => [$name, $email, $company, $status, $src, $med, $camp]) {
            $id = DB::insert('leads', [
                'country_id' => $countryId, 'name' => $name, 'email' => $email, 'company' => $company, 'status' => $status,
                'source' => 'Website Form', 'utm_source' => $src, 'utm_medium' => $med, 'utm_campaign' => $camp ?: null,
                'first_utm_source' => $src, 'first_utm_medium' => $med, 'product_id' => $products[$i % count($products)],
                'form_id' => $formId, 'pipeline_order' => $i, 'value' => [0, 15000, 42000, 126000, 63000][$i],
                'created_at' => date('Y-m-d H:i:s', time() - $i * 86400 * 2), 'cta_location' => 'Hero',
            ]);
            DB::insert('lead_activities', ['lead_id' => $id, 'type' => 'CREATED', 'summary' => 'Lead created from the Contact form (demo data)']);
        }
    }
}
