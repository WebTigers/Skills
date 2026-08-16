<?php
/**
 * UI/UX Pro Max (PHP port) — BM25 search over the UI/UX design-intelligence database.
 *
 * A dependency-free PHP 8 port of nextlevelbuilder/ui-ux-pro-max-skill's Python
 * search engine (core.py + search.py). Same CSV data, same BM25 ranking, same
 * token-optimized markdown output — no Composer, no npm, no Python.
 *
 * USAGE:
 *   php search.php "<query>" [--domain <d>] [--stack <s>] [-n <N>|--max-results <N>] [--json] [--full]
 *
 * DOMAINS: style, color, chart, landing, product, ux, typography, google-fonts,
 *          icons, gsap, react, web   (domain auto-detected when --domain omitted)
 * STACKS:  react, nextjs, vue, svelte, astro, swiftui, react-native, flutter,
 *          nuxtjs, nuxt-ui, html-tailwind, shadcn, jetpack-compose, threejs,
 *          angular, laravel, javafx, wpf, winui, avalonia, uno, uwp, tiger-php
 *
 * NOT PORTED (v1): the upstream `--design-system` generator mode (design_system.py)
 * is out of scope for this fork — search + stack modes only. Requesting
 * --design-system prints a notice and exits.
 *
 * FIDELITY: BM25 (k1=1.5, b=0.75), the tokenizer (lowercase, synonym
 * normalization, stopword filter, len>=2), CSV_CONFIG, the domain keyword
 * detector, per-domain query rewrites, score/coverage thresholds, exact-identity
 * routing for the style + landing domains, and the markdown formatter are ported
 * faithfully from core.py. Deviations are documented inline (see DEVIATION notes):
 * the per-process CSV/BM25 caches are dropped (a CLI run does one search), and the
 * stack legacy version-number parser is simplified to keyword intent.
 */

// ============ CONFIGURATION ============
const DATA_DIR   = __DIR__ . '/../data';
const MAX_RESULTS = 3;
const TRUNCATE_AT = 300;

const CSV_CONFIG = [
    'style' => [
        'file' => 'styles.csv',
        'search_cols' => ['Style ID', 'Style Category', 'Aliases', 'Keywords', 'Best For', 'Type', 'AI Prompt Keywords'],
        'output_cols' => ['Style ID', 'Style Category', 'Aliases', 'Status', 'Parent Style ID', 'Preferred Mode', 'Type', 'Keywords', 'Primary Colors', 'Effects & Animation', 'Best For', 'Light Mode ✓', 'Dark Mode ✓', 'Performance', 'Accessibility', 'Framework Compatibility', 'Complexity', 'AI Prompt Keywords', 'CSS/Technical Keywords', 'Implementation Checklist', 'Design System Variables'],
    ],
    'color' => [
        'file' => 'colors.csv',
        'search_cols' => ['Product Type', 'Notes'],
        'output_cols' => ['Product Type', 'Primary', 'On Primary', 'Secondary', 'On Secondary', 'Accent', 'On Accent', 'Background', 'Foreground', 'Card', 'Card Foreground', 'Muted', 'Muted Foreground', 'Border', 'Destructive', 'On Destructive', 'Ring', 'Notes'],
    ],
    'chart' => [
        'file' => 'charts.csv',
        'search_cols' => ['Data Type', 'Keywords', 'Best Chart Type', 'When to Use', 'When NOT to Use', 'Accessibility Notes'],
        'output_cols' => ['Data Type', 'Keywords', 'Best Chart Type', 'Secondary Options', 'When to Use', 'When NOT to Use', 'Data Volume Threshold', 'Color Guidance', 'Accessibility Grade', 'Accessibility Risk', 'Accessibility Notes', 'A11y Fallback', 'Library Recommendation', 'Interactive Level'],
    ],
    'landing' => [
        'file' => 'landing.csv',
        'search_cols' => ['Pattern ID', 'Pattern Name', 'Aliases', 'Keywords', 'Conversion Optimization', 'Section Order'],
        'output_cols' => ['Pattern ID', 'Pattern Name', 'Aliases', 'Keywords', 'Section Order', 'Primary CTA Placement', 'Color Strategy', 'Conversion Optimization'],
    ],
    'product' => [
        'file' => 'products.csv',
        'search_cols' => ['Product Type', 'Keywords', 'Primary Style Recommendation', 'Key Considerations'],
        'output_cols' => ['Product Type', 'Keywords', 'Primary Style Recommendation', 'Secondary Styles', 'Landing Page Pattern', 'Dashboard Style (if applicable)', 'Color Palette Focus'],
    ],
    'ux' => [
        'file' => 'ux-guidelines.csv',
        'search_cols' => ['Category', 'Issue', 'Description', 'Platform'],
        'output_cols' => ['Category', 'Issue', 'Platform', 'Description', 'Do', "Don't", 'Code Example Good', 'Code Example Bad', 'Severity'],
    ],
    'typography' => [
        'file' => 'typography.csv',
        'search_cols' => ['Font Pairing Name', 'Category', 'Mood/Style Keywords', 'Best For', 'Heading Font', 'Body Font'],
        'output_cols' => ['Font Pairing Name', 'Category', 'Heading Font', 'Body Font', 'Mood/Style Keywords', 'Best For', 'Google Fonts URL', 'CSS Import', 'Tailwind Config', 'Notes'],
    ],
    'icons' => [
        'file' => 'icons.csv',
        'search_cols' => ['Category', 'Icon Name', 'Keywords', 'Best For', 'Library'],
        'output_cols' => ['Category', 'Icon Name', 'Keywords', 'Library', 'Import Code', 'Usage', 'Best For', 'Style', 'Semantic Role', 'Allowed Contexts'],
    ],
    'gsap' => [
        'file' => 'motion.csv',
        'search_cols' => ['Category', 'Intensity Tier', 'Keywords', 'Trigger'],
        'output_cols' => ['Category', 'Intensity Tier', 'Trigger', 'Duration', 'Easing', 'GSAP Snippet', 'Framework Notes', 'Do', "Don't", 'Performance Notes'],
    ],
    'react' => [
        'file' => 'react-performance.csv',
        'search_cols' => ['Category', 'Issue', 'Keywords', 'Description'],
        'output_cols' => ['Category', 'Issue', 'Platform', 'Description', 'Do', "Don't", 'Code Example Good', 'Code Example Bad', 'Severity'],
    ],
    'web' => [
        'file' => 'app-interface.csv',
        'search_cols' => ['Category', 'Issue', 'Keywords', 'Description'],
        'output_cols' => ['Category', 'Issue', 'Platform', 'Description', 'Do', "Don't", 'Code Example Good', 'Code Example Bad', 'Severity'],
    ],
    'google-fonts' => [
        'file' => 'google-fonts.csv',
        'search_cols' => ['Family', 'Category', 'Stroke', 'Classifications', 'Keywords', 'Subsets', 'Designers'],
        'output_cols' => ['Family', 'Category', 'Stroke', 'Classifications', 'Styles', 'Variable Axes', 'Subsets', 'Designers', 'Popularity Rank', 'Google Fonts URL'],
    ],
];

// Output columns whose content (code samples, checklists) must never be
// hard-truncated for display — truncating mid-snippet destroys the value.
const UNTRUNCATED_COLS = [
    'Code Example Good', 'Code Example Bad', 'Code Good', 'Code Bad',
    'Implementation Checklist', 'Design System Variables', 'CSS Import',
    'Tailwind Config', 'GSAP Snippet',
];

const STACK_CONFIG = [
    'react'           => ['file' => 'stacks/react.csv'],
    'nextjs'          => ['file' => 'stacks/nextjs.csv'],
    'vue'             => ['file' => 'stacks/vue.csv'],
    'svelte'          => ['file' => 'stacks/svelte.csv'],
    'astro'           => ['file' => 'stacks/astro.csv'],
    'swiftui'         => ['file' => 'stacks/swiftui.csv'],
    'react-native'    => ['file' => 'stacks/react-native.csv'],
    'flutter'         => ['file' => 'stacks/flutter.csv'],
    'nuxtjs'          => ['file' => 'stacks/nuxtjs.csv'],
    'nuxt-ui'         => ['file' => 'stacks/nuxt-ui.csv'],
    'html-tailwind'   => ['file' => 'stacks/html-tailwind.csv'],
    'shadcn'          => ['file' => 'stacks/shadcn.csv'],
    'jetpack-compose' => ['file' => 'stacks/jetpack-compose.csv'],
    'threejs'         => ['file' => 'stacks/threejs.csv'],
    'angular'         => ['file' => 'stacks/angular.csv'],
    'laravel'         => ['file' => 'stacks/laravel.csv'],
    'javafx'          => ['file' => 'stacks/javafx.csv'],
    'wpf'             => ['file' => 'stacks/wpf.csv'],
    'winui'           => ['file' => 'stacks/winui.csv'],
    'avalonia'        => ['file' => 'stacks/avalonia.csv'],
    'uno'             => ['file' => 'stacks/uno.csv'],
    'uwp'             => ['file' => 'stacks/uwp.csv'],
    // Tiger/TigerZF (PHP) stack — added by this PHP fork.
    'tiger-php'       => ['file' => 'stacks/tiger-php.csv'],
];

const STACK_SEARCH_COLS = ['Category', 'Guideline', 'Description', 'Do', "Don't", 'Code Good', 'Code Bad'];
const STACK_OUTPUT_COLS = ['Category', 'Guideline', 'Description', 'Do', "Don't", 'Code Good', 'Code Bad', 'Severity', 'Docs URL', 'Applies To', 'Status', 'Verified At'];

// Stacks that have no "current" generation to prefer (legacy-only corpora).
const LEGACY_ONLY_STACKS = ['uwp'];
// Stacks that DO have a current generation (a "legacy" request has nothing to serve).
const STACK_HAS_CURRENT = [
    'react', 'nextjs', 'vue', 'svelte', 'astro', 'angular', 'html-tailwind',
    'shadcn', 'nuxtjs', 'nuxt-ui', 'react-native', 'flutter', 'swiftui',
    'jetpack-compose', 'avalonia', 'winui', 'javafx', 'threejs', 'laravel',
];

function available_stacks(): array { return array_keys(STACK_CONFIG); }

// ---- Search calibration (ported from core.py) ----------------------------
const DOMAIN_SCORE_FLOORS = [
    'style' => 4.3, 'landing' => 4.0, 'product' => 6.0, 'icons' => 5.8, 'react' => 3.3,
];

function search_thresholds(): array {
    $t = [];
    foreach (array_keys(CSV_CONFIG) as $domain) {
        $t[$domain] = [
            'min_score'    => DOMAIN_SCORE_FLOORS[$domain] ?? 0.0,
            'min_margin'   => 0.0,
            'min_coverage' => $domain === 'landing' ? 0.5 : 0.0,
        ];
    }
    return $t;
}
const STACK_THRESHOLD = ['min_score' => 3.6, 'min_margin' => 0.0, 'min_coverage' => 0.3333333333333333];
const NO_THRESHOLD    = ['min_score' => 0.0, 'min_margin' => 0.0, 'min_coverage' => 0.0];

const STYLE_IDENTITY_FIELDS   = ['Style ID', 'Style Category', 'Aliases'];
const LANDING_IDENTITY_FIELDS = ['Pattern ID', 'Pattern Name', 'Aliases'];

// Per-domain query rewrites: map a routing-only vocabulary term to a searchable
// synonym (or null = drop, no replacement). Ported from _DOMAIN_QUERY_REWRITES.
const DOMAIN_QUERY_REWRITES = [
    'color'        => ['color' => null, 'palette' => null, 'hex' => null, 'rgb' => null, 'token' => null, 'semantic' => null, 'destructive' => null, 'muted' => null, 'foreground' => null],
    'landing'      => ['testimonial' => 'testimonials'],
    'style'        => ['css' => null, 'implementation' => null, 'variable' => null, 'checklist' => null, 'tailwind' => null],
    'ux'           => ['ux' => 'accessibility', 'usability' => 'accessibility', 'wcag' => 'accessibility'],
    'google-fonts' => ['typography' => 'font'],
    'icons'        => ['lucide' => null, 'symbol' => null, 'glyph' => null, 'pictogram' => null],
    'gsap'         => ['gsap' => 'animation', 'quickto' => null, 'scrolltrigger' => 'scroll', 'flip plugin' => null, 'splittext' => null],
    'react'        => ['nextjs' => 'react', 'usecallback' => 'memoization', 'useeffect' => 'effects'],
    'web'          => ['aria' => 'accessibility', 'outline' => 'focus', 'semantic' => null, 'autocomplete' => 'input', 'preconnect' => null],
];

// ============ TOKENIZATION ============
const STOPWORDS = [
    'to', 'in', 'on', 'at', 'is', 'of', 'by', 'or', 'an', 'if', 'no', 'so',
    'do', 'be', 'we', 'it', 'as', 'the', 'and', 'for', 'are', 'was',
];

const SYNONYMS = [
    'q&a' => 'question answer',
    'e-commerce' => 'ecommerce',
    'dark-mode' => 'dark',
    'darkmode' => 'dark',
    'light-mode' => 'light',
    'lightmode' => 'light',
    'a11y' => 'accessibility',
    'nav' => 'navigation',
    'sign-up' => 'signup',
    'log-in' => 'login',
    'colour' => 'color',
    'colours' => 'colors',
    'customisation' => 'customization',
    'organisation' => 'organization',
    'behaviour' => 'behavior',
    'ux/ui' => 'ux ui',
];

/** Longest-first synonym patterns (variant => canonical), matched at word boundaries. */
function synonym_patterns(): array {
    static $patterns = null;
    if ($patterns !== null) return $patterns;
    $items = SYNONYMS;
    uksort($items, fn($a, $b) => strlen($b) <=> strlen($a));
    $patterns = [];
    foreach ($items as $variant => $canonical) {
        $patterns[] = ['/(?<!\w)' . preg_quote($variant, '/') . '(?!\w)/iu', $canonical];
    }
    return $patterns;
}

/** Apply longest-first synonym substitution at token boundaries. */
function normalize_text(string $text): string {
    foreach (synonym_patterns() as [$re, $canonical]) {
        $text = preg_replace($re, $canonical, $text);
    }
    return $text;
}

// ============ BM25 IMPLEMENTATION ============
class BM25
{
    private float $k1;
    private float $b;
    /** @var array<int,array<int,string>> */
    private array $corpus = [];
    /** @var int[] */
    private array $docLengths = [];
    private float $avgdl = 0.0;
    /** @var array<string,float> */
    private array $idf = [];
    /** @var array<string,int> */
    public array $docFreqs = [];
    private int $N = 0;
    /** @var array<int,array<string,int>> */
    private array $termFreqs = [];

    public function __construct(float $k1 = 1.5, float $b = 0.75)
    {
        $this->k1 = $k1;
        $this->b = $b;
    }

    /** Lowercase, normalize synonyms, strip punctuation, split, drop stopwords + 1-char tokens. */
    public function tokenize(string $text): array
    {
        $text = normalize_text(mb_strtolower($text));
        // Python re \w is unicode; approximate with letters/numbers/underscore.
        $text = preg_replace('/[^\p{L}\p{N}_\s]/u', ' ', $text);
        $words = preg_split('/\s+/u', trim($text));
        $out = [];
        foreach ($words as $w) {
            if ($w === '') continue;
            if (mb_strlen($w) >= 2 && !in_array($w, STOPWORDS, true)) {
                $out[] = $w;
            }
        }
        return $out;
    }

    /** @param string[] $documents */
    public function fit(array $documents): void
    {
        $this->corpus = array_map(fn($d) => $this->tokenize($d), $documents);
        $this->N = count($this->corpus);
        if ($this->N === 0) return;

        $this->docLengths = array_map('count', $this->corpus);
        $sum = array_sum($this->docLengths);
        $this->avgdl = ($sum / $this->N) ?: 1.0;

        $this->termFreqs = [];
        $this->docFreqs = [];
        foreach ($this->corpus as $doc) {
            $tf = [];
            foreach ($doc as $word) {
                $tf[$word] = ($tf[$word] ?? 0) + 1;
            }
            $this->termFreqs[] = $tf;
            foreach (array_keys($tf) as $word) {
                $this->docFreqs[$word] = ($this->docFreqs[$word] ?? 0) + 1;
            }
        }
        foreach ($this->docFreqs as $word => $freq) {
            $this->idf[$word] = log(($this->N - $freq + 0.5) / ($freq + 0.5) + 1);
        }
    }

    /** @return array<int,array{0:int,1:float}> sorted by score desc (stable). */
    public function score(string $query): array
    {
        $queryTokens = $this->tokenize($query);
        $scores = [];
        for ($idx = 0; $idx < $this->N; $idx++) {
            $score = 0.0;
            $docLen = $this->docLengths[$idx];
            $tf = $this->termFreqs[$idx];
            foreach ($queryTokens as $token) {
                if (!isset($this->idf[$token])) continue;
                $f = $tf[$token] ?? 0;
                if ($f === 0) continue;
                $idf = $this->idf[$token];
                $numerator = $f * ($this->k1 + 1);
                $denominator = $f + $this->k1 * (1 - $this->b + $this->b * $docLen / $this->avgdl);
                $score += $idf * $numerator / $denominator;
            }
            $scores[] = [$idx, $score];
        }
        // PHP 8 usort is stable → ties keep original (idx) order, matching Python.
        usort($scores, fn($a, $b) => $b[1] <=> $a[1]);
        return $scores;
    }

    /** @return string[] */
    public function vocabulary(): array { return array_keys($this->idf); }

    public function passesThreshold(string $query, array $threshold): bool
    {
        $ranked = $this->score($query);
        $top = $ranked[0][1] ?? 0.0;
        $runnerUp = $ranked[1][1] ?? 0.0;
        return $top > $threshold['min_score']
            && query_coverage($this, $query) >= $threshold['min_coverage']
            && ($threshold['min_margin'] <= 0 || $top - $runnerUp >= $threshold['min_margin']);
    }
}

// ============ CSV LOADING ============
/**
 * Read a CSV into a list of header-keyed rows. Missing cells become "".
 * Uses "" as the escape char to match Python csv semantics (no backslash escaping).
 * @return array<int,array<string,string>>
 */
function load_rows(string $path): array
{
    if (!is_file($path)) return [];
    $fh = @fopen($path, 'r');
    if ($fh === false) return [];
    $header = fgetcsv($fh, 0, ',', '"', '');
    if ($header === false) { fclose($fh); return []; }
    // Strip a UTF-8 BOM off the first header cell if present.
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    $rows = [];
    while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        // Skip fully blank lines (fgetcsv yields [null] or [''] ).
        if (count($r) === 1 && ($r[0] === null || $r[0] === '')) continue;
        $row = [];
        foreach ($header as $i => $col) {
            $val = $r[$i] ?? '';
            $row[$col] = $val === null ? '' : $val;
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

// ============ HELPERS ============
function query_coverage(BM25 $index, string $query): float
{
    $tokens = array_unique($index->tokenize($query));
    if (!$tokens) return 0.0;
    $vocab = array_flip($index->vocabulary());
    $hit = 0;
    foreach ($tokens as $t) if (isset($vocab[$t])) $hit++;
    return $hit / count($tokens);
}

/** difflib.SequenceMatcher.ratio() approximation via similar_text (0..1). */
function seq_ratio(string $a, string $b): float
{
    if ($a === '' && $b === '') return 1.0;
    similar_text($a, $b, $pct);
    return $pct / 100.0;
}

/** True if $phrase occurs in $text at word boundaries (word phrase) or as substring. */
function contains_phrase(string $text, string $phrase): bool
{
    if (preg_match('/\w/u', $phrase)) {
        return (bool) preg_match('/(?<!\w)' . preg_quote($phrase, '/') . '(?!\w)/u', $text);
    }
    return str_contains($text, $phrase);
}

/** @return array<string,string> keep only output cols present in the row. */
function project_row(array $row, array $cols): array
{
    $out = [];
    foreach ($cols as $c) {
        if (array_key_exists($c, $row)) $out[$c] = $row[$c];
    }
    return $out;
}

/** Non-empty public identities from ordinary + Aliases (pipe-split) fields. */
function row_identities(array $row, array $fields): array
{
    $identities = [];
    foreach ($fields as $field) {
        $values = ($field === 'Aliases')
            ? explode('|', $row[$field] ?? '')
            : [$row[$field] ?? ''];
        foreach ($values as $v) {
            $v = trim($v);
            if ($v !== '') $identities[] = $v;
        }
    }
    return $identities;
}

function valid_max_results(mixed $value): bool
{
    return is_int($value) && $value >= 1 && $value <= 20;
}

// ---- Product-domain keyword list, loaded from products.csv --------------
function load_product_keywords(): array
{
    $seed = ['saas', 'ecommerce', 'fintech', 'healthcare', 'gaming', 'portfolio',
        'crypto', 'fitness', 'marketplace', 'banking', 'cybersecurity',
        'education', 'travel', 'restaurant', 'real estate', 'social media',
        'beauty', 'spa', 'salon', 'wellness', 'booking'];
    $path = DATA_DIR . '/' . CSV_CONFIG['product']['file'];
    if (!is_file($path)) return $seed;
    $rows = load_rows($path);
    $keywords = array_flip($seed);
    foreach ($rows as $row) {
        $label = preg_replace('/\([^)]*\)/', '', $row['Product Type'] ?? '');
        $label = mb_strtolower(trim($label));
        if (mb_strlen($label) >= 4) $keywords[$label] = true;
    }
    $keywords = array_keys($keywords);
    usort($keywords, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    return $keywords;
}

function domain_keywords(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [
        'color' => ['color', 'palette', 'hex', 'rgb', 'token', 'semantic', 'accent', 'destructive', 'muted', 'foreground'],
        'chart' => ['time series', 'chart', 'graph', 'visualization', 'trend', 'bar chart', 'pie', 'scatter', 'heatmap', 'funnel', 'forecast'],
        'landing' => ['landing', 'page', 'cta', 'conversion', 'hero', 'testimonial', 'pricing', 'section'],
        'product' => load_product_keywords(),
        'style' => ['style', 'design', 'ui', 'minimalism', 'glassmorphism', 'neumorphism', 'brutalism', 'dark mode', 'flat', 'aurora', 'css', 'implementation', 'variable', 'checklist', 'tailwind'],
        'ux' => ['ux', 'usability', 'accessibility', 'wcag', 'touch', 'scroll', 'animation', 'keyboard', 'navigation', 'mobile'],
        'typography' => ['font pairing', 'typography pairing', 'heading font', 'body font'],
        'google-fonts' => ['google font', 'font family', 'font weight', 'font style', 'variable font', 'noto', 'font for', 'find font', 'font subset', 'font language', 'monospace font', 'serif font', 'sans serif font', 'display font', 'handwriting font', 'font', 'typography', 'serif', 'sans'],
        'icons' => ['icon', 'icons', 'lucide', 'phosphor', 'heroicons', 'symbol', 'glyph', 'pictogram', 'svg icon'],
        'gsap' => ['gsap', 'quickto', 'scrolltrigger', 'stagger', 'magnetic cursor', 'parallax', 'page transition', 'scroll reveal', 'scroll-triggered', 'scrollytelling', 'flip plugin', 'splittext', 'shimmer', 'skeleton loader'],
        'react' => ['react', 'next.js', 'nextjs', 'suspense', 'memo', 'usecallback', 'useeffect', 'rerender', 'bundle', 'waterfall', 'barrel', 'dynamic import', 'rsc', 'server component'],
        'web' => ['aria', 'focus', 'outline', 'semantic', 'virtualize', 'autocomplete', 'form', 'input type', 'preconnect', 'drag reorder', 'single pointer', 'touch target', 'native accessibility'],
    ];
    return $cache;
}

// Domains checked in this fixed order when scores tie (deterministic).
const DOMAIN_TIEBREAK_ORDER = [
    'ux', 'product', 'style', 'color', 'typography', 'google-fonts',
    'chart', 'landing', 'icons', 'gsap', 'react', 'web',
];

function detect_domain(string $query, bool $returnScores = false): array|string
{
    $queryLower = normalize_text(mb_strtolower($query));
    $keywords = domain_keywords();
    $rank = array_flip(DOMAIN_TIEBREAK_ORDER);

    $scores = [];
    foreach ($keywords as $domain => $kws) {
        $total = 0.0;
        foreach ($kws as $kw) {
            if (contains_phrase($queryLower, $kw)) {
                $specificity = max(1, count(explode(' ', $kw)));
                $total += $domain !== 'product' ? 2.0 * $specificity : $specificity;
            }
        }
        $scores[$domain] = $total;
    }
    if (preg_match('/(?<!\w)#[0-9a-f]{3,8}(?!\w)/i', $queryLower)) {
        $scores['color'] += 2.0;
    }

    $ranked = [];
    foreach ($scores as $domain => $score) $ranked[] = [$domain, $score];
    usort($ranked, function ($a, $b) use ($rank) {
        if ($a[1] !== $b[1]) return $b[1] <=> $a[1];
        // reverse=True on (score, -rank): higher -rank first => lower rank number first.
        return ($rank[$a[0]] ?? 999) <=> ($rank[$b[0]] ?? 999);
    });

    [$bestDomain, $bestScore] = $ranked[0];
    $result = $bestScore > 0 ? $bestDomain : 'style';
    if ($returnScores) {
        $runnerUp = (count($ranked) > 1 && $ranked[1][1] > 0) ? $ranked[1][0] : null;
        return [$result, $runnerUp];
    }
    return $result;
}

/** @return array{0:string,1:array<int,string>} rewritten query + list of "kw->repl" notes. */
function rewrite_query_for_domain(string $query, ?string $domain, BM25 $index): array
{
    $kw = domain_keywords();
    if (!$domain || !isset($kw[$domain])) return [$query, []];
    $normalized = normalize_text(mb_strtolower($query));
    $vocab = array_flip($index->vocabulary());
    $rewrites = [];
    $replacements = [];
    foreach ($kw[$domain] as $keyword) {
        if (!contains_phrase($normalized, $keyword)) continue;
        // Skip if the keyword already tokenizes into the corpus vocabulary.
        $inVocab = false;
        foreach ($index->tokenize($keyword) as $t) {
            if (isset($vocab[$t])) { $inVocab = true; break; }
        }
        if ($inVocab) continue;
        $replacement = DOMAIN_QUERY_REWRITES[$domain][$keyword] ?? null;
        if ($replacement) {
            $rewrites[] = "$keyword->$replacement";
            $replacements[] = $replacement;
        }
    }
    if (!$replacements) return [$query, []];
    $replacements = array_unique($replacements);
    sort($replacements);
    $rewrites = array_unique($rewrites);
    sort($rewrites);
    return [$query . ' ' . implode(' ', $replacements), $rewrites];
}

// ---- Style identity routing ----------------------------------------------
function style_identity(array $rows, string $query, bool $allowContained = true): ?array
{
    $folded = mb_strtolower(trim($query));
    $normFolded = normalize_text($folded);
    preg_match_all('/\w+/u', $normFolded, $m);
    $queryTokens = array_flip($m[0]);
    $generic = array_flip(['app', 'design', 'interface', 'style', 'system', 'ui']);

    $candidates = [];
    foreach ($rows as $row) {
        $identities = row_identities($row, STYLE_IDENTITY_FIELDS);
        $foldedIdentities = array_map(fn($x) => mb_strtolower($x), $identities);
        if (in_array($folded, $foldedIdentities, true)) return $row;
        if (!$allowContained) continue;
        foreach ($identities as $identity) {
            preg_match_all('/\w+/u', normalize_text(mb_strtolower($identity)), $im);
            $identityTokens = $im[0];
            if (!$identityTokens) continue;
            // identityTokens subset of queryTokens?
            $subset = true;
            foreach ($identityTokens as $it) {
                if (!isset($queryTokens[$it])) { $subset = false; break; }
            }
            if (!$subset) continue;
            $hasLong = false;
            foreach ($identityTokens as $it) if (mb_strlen($it) >= 4) { $hasLong = true; break; }
            if (!$hasLong) continue;
            $distinctive = 0;
            foreach (array_unique($identityTokens) as $it) if (!isset($generic[$it])) $distinctive++;
            $candidates[] = [$distinctive, count($identityTokens), mb_strlen($identity), $row];
        }
    }
    if (!$candidates) return null;
    usort($candidates, function ($a, $b) {
        if ($a[0] !== $b[0]) return $b[0] <=> $a[0];
        return $b[1] <=> $a[1];
    });
    $best = [$candidates[0][0], $candidates[0][1], $candidates[0][2]];
    $bestRows = [];
    foreach ($candidates as $c) {
        if ([$c[0], $c[1], $c[2]] === $best) {
            $bestRows[$c[3]['Style ID'] ?? ''] = $c[3];
        }
    }
    return count($bestRows) === 1 ? array_values($bestRows)[0] : null;
}

/** @return array{0:?array,1:?array} [destinationRow, redirectInfo] */
function style_search_destination(array $rows, ?array $matched): array
{
    if ($matched === null || ($matched['Status'] ?? 'active') !== 'deprecated') {
        return [$matched, null];
    }
    $parentId = trim($matched['Parent Style ID'] ?? '');
    if ($parentId !== '') {
        foreach ($rows as $row) if (($row['Style ID'] ?? '') === $parentId) return [$row, null];
        return [null, null];
    }
    $domain = trim($matched['Replacement Domain'] ?? '');
    $replacementId = trim($matched['Replacement ID'] ?? '');
    if ($domain === 'style' && $replacementId !== '') {
        foreach ($rows as $row) if (($row['Style ID'] ?? '') === $replacementId) return [$row, null];
        return [null, null];
    }
    if ($domain !== '' && $replacementId !== '') {
        return [null, ['domain' => $domain, 'id' => $replacementId]];
    }
    return [null, null];
}

function exact_row_identity(array $rows, string $query, array $fields): ?array
{
    $folded = mb_strtolower(trim($query));
    $matches = [];
    foreach ($rows as $row) {
        $foldedIdentities = array_map(fn($x) => mb_strtolower($x), row_identities($row, $fields));
        if (in_array($folded, $foldedIdentities, true)) $matches[] = $row;
    }
    return count($matches) === 1 ? $matches[0] : null;
}

// ---- Suggestions ----------------------------------------------------------
function suggest_terms(?BM25 $bm25, string $query, int $limit = 6, ?array $threshold = null): array
{
    if ($bm25 === null) return [];
    $queryTokens = array_unique($bm25->tokenize($query));
    if (!$queryTokens) return [];
    $qflip = array_flip($queryTokens);

    $candidates = [];
    foreach ($bm25->vocabulary() as $term) {
        if (isset($qflip[$term])) continue;
        $sim = 0.0;
        foreach ($queryTokens as $tok) $sim = max($sim, seq_ratio($tok, $term));
        if ($sim >= 0.72 && ($threshold === null || $bm25->passesThreshold($term, $threshold))) {
            $candidates[] = [-$sim, -($bm25->docFreqs[$term] ?? 0), $term];
        }
    }
    usort($candidates, function ($a, $b) {
        return [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]];
    });
    return array_map(fn($c) => $c[2], array_slice($candidates, 0, $limit));
}

function suggest_identities(array $rows, string $query, array $fields, int $limit = 6): array
{
    $tok = new BM25();
    $queryTokens = array_unique($tok->tokenize($query));
    if (!$queryTokens) return [];
    $folded = mb_strtolower(trim($query));
    $candidates = [];
    foreach ($rows as $row) {
        foreach (row_identities($row, $fields) as $identity) {
            $identityTokens = $tok->tokenize($identity);
            if (!$identityTokens) continue;
            $sim = 0.0;
            foreach ($queryTokens as $src) {
                foreach ($identityTokens as $tgt) $sim = max($sim, seq_ratio($src, $tgt));
            }
            if ($sim >= 0.72 && mb_strtolower($identity) !== $folded) {
                $candidates[$identity] = [-$sim, count($identityTokens), $identity];
            }
        }
    }
    $vals = array_values($candidates);
    usort($vals, fn($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
    return array_map(fn($c) => $c[2], array_slice($vals, 0, $limit));
}

// ============ CORE SEARCH ============
function exact_match_diagnostic(string $query, string $reason): array
{
    return [
        'normalized_query' => normalize_text($query),
        'search_query' => $query,
        'query_rewrites' => [],
        'top_score' => 0.0,
        'runner_up_score' => 0.0,
        'margin' => 0.0,
        'token_coverage' => 1.0,
        'abstained' => false,
        'reason' => $reason,
    ];
}

/**
 * @return array{0:array<int,array>,1:?BM25,2:array}
 */
function search_csv_detailed(string $filepath, array $searchCols, array $outputCols, string $query, int $maxResults, ?array $threshold = null, ?string $routingDomain = null, ?callable $rowFilter = null): array
{
    if (!is_file($filepath)) return [[], null, ['reason' => 'missing-file']];
    $data = load_rows($filepath);
    if (!$data) return [[], null, ['reason' => 'empty-data']];
    if ($rowFilter !== null) {
        $data = array_values(array_filter($data, $rowFilter));
        if (!$data) return [[], null, ['reason' => 'empty-data']];
    }

    $documents = [];
    foreach ($data as $row) {
        $parts = [];
        foreach ($searchCols as $c) $parts[] = (string) ($row[$c] ?? '');
        $documents[] = implode(' ', $parts);
    }
    $bm25 = new BM25();
    $bm25->fit($documents);

    [$searchQuery, $rewrites] = rewrite_query_for_domain($query, $routingDomain, $bm25);
    $ranked = $bm25->score($searchQuery);
    $threshold = $threshold ?? NO_THRESHOLD;
    $topScore = $ranked[0][1] ?? 0.0;
    $runnerUp = $ranked[1][1] ?? 0.0;
    $coverage = query_coverage($bm25, $searchQuery);
    $abstain = ($topScore <= $threshold['min_score'])
        || ($coverage < $threshold['min_coverage'])
        || ($threshold['min_margin'] > 0 && $topScore - $runnerUp < $threshold['min_margin']);

    $results = [];
    if (!$abstain) {
        foreach (array_slice($ranked, 0, $maxResults) as [$idx, $score]) {
            if ($score <= 0) continue;
            $results[] = project_row($data[$idx], $outputCols);
        }
    }
    $diag = [
        'normalized_query' => normalize_text($query),
        'search_query' => $searchQuery,
        'query_rewrites' => $rewrites,
        'top_score' => $topScore,
        'runner_up_score' => $runnerUp,
        'margin' => $topScore - $runnerUp,
        'token_coverage' => $coverage,
        'abstained' => $abstain,
        'reason' => $abstain ? 'low-confidence' : 'matched',
    ];
    return [$results, $bm25, $diag];
}

function search(string $query, ?string $domain = null, int $maxResults = MAX_RESULTS, bool $diagnostics = false): array
{
    if (!valid_max_results($maxResults)) {
        return ['error' => 'max_results must be an integer from 1 to 20', 'domain' => $domain];
    }
    $autoDetected = $domain === null;
    $runnerUp = null;
    $redirect = null;
    $exactStyle = null;
    $styleRows = null;
    $landingRows = null;

    if ($domain === null) {
        $stylePath = DATA_DIR . '/' . CSV_CONFIG['style']['file'];
        $styleRows = load_rows($stylePath);
        $matchedStyle = style_identity($styleRows, $query, false);
        if ($matchedStyle !== null) {
            $domain = 'style';
            [$exactStyle, $redirect] = style_search_destination($styleRows, $matchedStyle);
        } else {
            [$domain, $runnerUp] = detect_domain($query, true);
        }
    }

    $searchDomain = isset(CSV_CONFIG[$domain]) ? $domain : 'style';
    $config = CSV_CONFIG[$searchDomain];
    $filepath = DATA_DIR . '/' . $config['file'];

    if (!is_file($filepath)) {
        return ['error' => "File not found: {$filepath}", 'domain' => $domain];
    }

    if ($searchDomain === 'style' && $exactStyle === null && $redirect === null) {
        if ($styleRows === null) $styleRows = load_rows($filepath);
        [$exactStyle, $redirect] = style_search_destination($styleRows, style_identity($styleRows, $query, true));
    } elseif ($searchDomain === 'landing') {
        $landingRows = load_rows($filepath);
        $exactStyle = exact_row_identity($landingRows, $query, LANDING_IDENTITY_FIELDS);
    }

    $bm25 = null;
    if ($exactStyle !== null) {
        $results = [project_row($exactStyle, $config['output_cols'])];
        $diagnostic = exact_match_diagnostic($query, 'exact-identity');
    } elseif ($redirect !== null) {
        $results = [];
        $diagnostic = ['abstained' => true, 'reason' => 'cross-domain-redirect'];
    } else {
        $rowFilter = $searchDomain === 'style'
            ? fn($row) => ($row['Status'] ?? 'active') === 'active'
            : null;
        [$results, $bm25, $diagnostic] = search_csv_detailed(
            $filepath, $config['search_cols'], $config['output_cols'], $query,
            $maxResults, search_thresholds()[$searchDomain], $searchDomain, $rowFilter
        );
    }

    if ($searchDomain === 'icons' && contains_phrase(normalize_text(mb_strtolower($query)), 'lucide')) {
        $results = [];
        $diagnostic['abstained'] = true;
        $diagnostic['reason'] = 'unsupported-library';
    }

    $out = [
        'domain' => $domain,
        'query' => $query,
        'file' => $config['file'],
        'count' => count($results),
        'results' => $results,
    ];
    if ($autoDetected) {
        $out['auto_detected'] = true;
        if ($runnerUp) $out['runner_up_domain'] = $runnerUp;
    }
    if ($redirect !== null) $out['redirect'] = $redirect;
    if (!empty($diagnostic['error'])) $out['error'] = $diagnostic['error'];
    if (!$results) {
        if ($searchDomain === 'landing') {
            $out['suggestions'] = suggest_identities($landingRows ?? [], $query, LANDING_IDENTITY_FIELDS);
        } else {
            $out['suggestions'] = suggest_terms($bm25, $query, 6, search_thresholds()[$searchDomain]);
        }
    }
    if ($diagnostics) $out['diagnostics'] = $diagnostic;
    return $out;
}

// ============ STACK SEARCH ============
/**
 * DEVIATION: core.py parses explicit framework version numbers in the query to
 * decide legacy vs current. This port simplifies to intent keywords
 * (legacy/deprecated vs migrate/upgrade/modern/current), which covers the common
 * case since the shipped stack CSVs are single-generation ("active"). shadcn
 * base-library (radix/base-ui/react-aria) sub-filtering is also not ported.
 */
function stack_query_requests_legacy(string $query, string $stack): bool
{
    $normalized = normalize_text(mb_strtolower(trim($query)));
    if (in_array($stack, LEGACY_ONLY_STACKS, true)) return true;
    if (preg_match('/\b(?:migrat\w*|upgrad\w*|replac\w*|instead|modern|current)\b/u', $normalized)) {
        return false;
    }
    return (bool) preg_match('/\b(?:legacy|deprecated)\b/u', $normalized);
}

/** @return array{0:callable,1:string} [rowFilter, variant] */
function stack_row_filter(array $rows, string $query, string $stack): array
{
    $statuses = [];
    foreach ($rows as $row) $statuses[$row['Status'] ?? 'unverified'] = true;
    $hasLegacy = isset($statuses['deprecated']);
    $requestsLegacy = stack_query_requests_legacy($query, $stack);

    if ($hasLegacy && $requestsLegacy) {
        return [fn($row) => ($row['Status'] ?? '') === 'deprecated', 'legacy-only'];
    }
    if ($requestsLegacy && in_array($stack, STACK_HAS_CURRENT, true)) {
        return [fn($row) => false, 'legacy-unavailable'];
    }
    if (isset($statuses['active'])) {
        return [fn($row) => ($row['Status'] ?? '') === 'active', 'current-only'];
    }
    return [fn($row) => ($row['Status'] ?? 'unverified') !== 'deprecated', 'non-legacy'];
}

/** Resolve a standalone API identifier even when its BM25 IDF is low. */
function exact_stack_identifier(array $rows, string $query, callable $rowFilter): ?array
{
    $identifier = trim($query);
    if (mb_strlen($identifier) < 6 || preg_match('/\s/u', $identifier)) return null;
    $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($identifier, '/') . '(?![A-Za-z0-9_])/i';
    $fields = ['Guideline', 'Description', 'Do', "Don't", 'Code Good', 'Code Bad'];
    $matches = [];
    foreach ($rows as $row) {
        if (!$rowFilter($row)) continue;
        foreach ($fields as $f) {
            if (preg_match($pattern, $row[$f] ?? '')) { $matches[] = $row; break; }
        }
    }
    return count($matches) === 1 ? $matches[0] : null;
}

function search_stack(string $query, string $stack, int $maxResults = MAX_RESULTS, bool $diagnostics = false): array
{
    if (!valid_max_results($maxResults)) {
        return ['error' => 'max_results must be an integer from 1 to 20', 'stack' => $stack];
    }
    if (!isset(STACK_CONFIG[$stack])) {
        return ['error' => "Unknown stack: {$stack}. Available: " . implode(', ', available_stacks())];
    }
    $filepath = DATA_DIR . '/' . STACK_CONFIG[$stack]['file'];
    if (!is_file($filepath)) {
        return ['error' => "Stack file not found: {$filepath}", 'stack' => $stack];
    }

    $rows = load_rows($filepath);
    [$rowFilter, $variant] = stack_row_filter($rows, $query, $stack);
    $threshold = $variant === 'legacy-only' ? NO_THRESHOLD : STACK_THRESHOLD;

    $bm25 = null;
    $exact = exact_stack_identifier($rows, $query, $rowFilter);
    if ($exact !== null) {
        $results = [project_row($exact, STACK_OUTPUT_COLS)];
        $diagnostic = exact_match_diagnostic($query, 'exact-identifier');
    } else {
        [$results, $bm25, $diagnostic] = search_csv_detailed(
            $filepath, STACK_SEARCH_COLS, STACK_OUTPUT_COLS, $query,
            $maxResults, $threshold, null, $rowFilter
        );
    }

    $out = [
        'domain' => 'stack',
        'stack' => $stack,
        'query' => $query,
        'file' => STACK_CONFIG[$stack]['file'],
        'count' => count($results),
        'results' => $results,
    ];
    if (!empty($diagnostic['error'])) $out['error'] = $diagnostic['error'];
    if (!$results) $out['suggestions'] = suggest_terms($bm25, $query, 6, $threshold);
    if ($diagnostics) $out['diagnostics'] = $diagnostic;
    return $out;
}

// ============ OUTPUT FORMATTING ============
function format_output(array $result, bool $full = false): string
{
    if (isset($result['error'])) return 'Error: ' . $result['error'];

    $output = [];
    if (!empty($result['stack'])) {
        $output[] = '## UI Pro Max Stack Guidelines';
        $output[] = "**Stack:** {$result['stack']} | **Query:** {$result['query']}";
    } else {
        $output[] = '## UI Pro Max Search Results';
        $domainNote = $result['domain'];
        if (!empty($result['auto_detected'])) {
            $domainNote .= ' (auto-detected';
            if (!empty($result['runner_up_domain'])) $domainNote .= ", runner-up: {$result['runner_up_domain']}";
            $domainNote .= ')';
        }
        $output[] = "**Domain:** {$domainNote} | **Query:** {$result['query']}";
    }
    $output[] = "**Source:** {$result['file']} | **Found:** {$result['count']} results\n";

    if ($result['count'] === 0) {
        if (!empty($result['redirect'])) {
            $r = $result['redirect'];
            $output[] = "This legacy style label is now modeled in the "
                . "`{$r['domain']}` domain as `{$r['id']}`. "
                . "Search that domain instead of treating a page composition as a visual style.";
            return implode("\n", $output);
        }
        $output[] = "No matches. This is not a match with an empty value -- the query "
            . "did not hit the database. Retry with broader/different keywords "
            . "before falling back to general defaults, and say explicitly that "
            . "no database match was found if you do fall back.";
        $suggestions = $result['suggestions'] ?? [];
        if ($suggestions) $output[] = '**Closest known terms:** ' . implode(', ', $suggestions);
        return implode("\n", $output);
    }

    $i = 1;
    foreach ($result['results'] as $row) {
        $output[] = "### Result {$i}";
        foreach ($row as $key => $value) {
            $valueStr = (string) $value;
            if (!$full && !in_array($key, UNTRUNCATED_COLS, true) && mb_strlen($valueStr) > TRUNCATE_AT) {
                $valueStr = mb_substr($valueStr, 0, TRUNCATE_AT) . '...';
            }
            $output[] = "- **{$key}:** {$valueStr}";
        }
        $output[] = '';
        $i++;
    }
    return implode("\n", $output);
}

// ============ CLI ============
function print_usage(): void
{
    $stacks = implode(', ', available_stacks());
    fwrite(STDERR, <<<TXT
UI/UX Pro Max (PHP) — design-intelligence search.

Usage:
  php search.php "<query>" [--domain <d>] [--stack <s>] [-n <N>|--max-results <N>] [--json] [--full]

Domains: style, color, chart, landing, product, ux, typography, google-fonts, icons, gsap, react, web
Stacks:  {$stacks}

Options:
  -d, --domain <d>        Force a search domain (else auto-detected)
  -s, --stack <s>         Search a stack's guidelines instead of the domain database
  -n, --max-results <N>   Max results, 1-20 (default: 3)
      --json              Emit JSON instead of markdown
      --full              Do not truncate long field values in markdown output
  -h, --help              Show this help

TXT);
}

function main(array $argv): int
{
    $args = array_slice($argv, 1);
    $query = null;
    $domain = null;
    $stack = null;
    $maxResults = MAX_RESULTS;
    $json = false;
    $full = false;
    $designSystem = false;

    for ($i = 0; $i < count($args); $i++) {
        $a = $args[$i];
        switch (true) {
            case $a === '-h' || $a === '--help':
                print_usage();
                return 0;
            case $a === '--json':
                $json = true; break;
            case $a === '--full':
                $full = true; break;
            case $a === '--design-system' || $a === '-ds':
                $designSystem = true; break;
            case $a === '-d' || $a === '--domain':
                $domain = $args[++$i] ?? null; break;
            case str_starts_with($a, '--domain='):
                $domain = substr($a, 9); break;
            case $a === '-s' || $a === '--stack':
                $stack = $args[++$i] ?? null; break;
            case str_starts_with($a, '--stack='):
                $stack = substr($a, 8); break;
            case $a === '-n' || $a === '--max-results':
                $maxResults = (int) ($args[++$i] ?? MAX_RESULTS); break;
            case str_starts_with($a, '--max-results='):
                $maxResults = (int) substr($a, 14); break;
            default:
                if ($query === null && !str_starts_with($a, '-')) {
                    $query = $a;
                } elseif ($query === null) {
                    fwrite(STDERR, "Unknown option before query: {$a}\n");
                    print_usage();
                    return 2;
                }
                // Ignore stray extra positionals (Python argparse would error;
                // we keep the first token as the query and move on).
        }
    }

    if ($designSystem) {
        fwrite(STDERR, "The --design-system generator mode is not ported in this PHP fork (v1). "
            . "Use search + stack modes only. See SKILL.md \"This fork's scope\".\n");
        return 2;
    }

    if ($query === null || $query === '') {
        fwrite(STDERR, "Error: a search query is required.\n\n");
        print_usage();
        return 2;
    }

    if ($domain !== null && !isset(CSV_CONFIG[$domain])) {
        fwrite(STDERR, "Error: unknown domain '{$domain}'. Choices: " . implode(', ', array_keys(CSV_CONFIG)) . "\n");
        return 2;
    }
    if ($stack !== null && !isset(STACK_CONFIG[$stack])) {
        fwrite(STDERR, "Error: unknown stack '{$stack}'. Choices: " . implode(', ', available_stacks()) . "\n");
        return 2;
    }
    if (!valid_max_results($maxResults)) {
        fwrite(STDERR, "Error: --max-results must be an integer from 1 to 20.\n");
        return 2;
    }

    if ($stack !== null) {
        $result = search_stack($query, $stack, $maxResults);
    } else {
        $result = search($query, $domain, $maxResults);
    }

    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    } else {
        echo format_output($result, $full), "\n";
    }
    return 0;
}

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    exit(main($argv));
}
