<?php
$pageTitle = ($blog['meta_title'] ?? $blog['title'] ?? 'Blog') . ' — RCS Print';
$pageDesc = $blog['meta_description'] ?? $blog['excerpt'] ?? 'RCS Print blog article.';
$pageOgType = 'article';
$pageImage = trim((string)($blog['featured_image'] ?? '')) ?: '/assets/images/rcs-graphic-logo.png';
$blogBaseUrl = defined('APP_URL') ? rtrim((string)APP_URL, '/') : '';
$blogSchemaImage = preg_match('#^https?://#i', $pageImage) ? $pageImage : ($blogBaseUrl . '/' . ltrim($pageImage, '/'));
$blogPublishedRaw = (string)($blog['published_at'] ?? $blog['created_at'] ?? 'now');
$blogModifiedRaw = (string)($blog['updated_at'] ?? $blog['published_at'] ?? $blogPublishedRaw);
$pageSchema = [[
  '@context' => 'https://schema.org',
  '@type' => 'BlogPosting',
  'headline' => (string)($blog['title'] ?? 'RCS Print Blog'),
  'description' => strip_tags((string)$pageDesc),
  'image' => $blogSchemaImage,
  'datePublished' => date('c', strtotime($blogPublishedRaw) ?: time()),
  'dateModified' => date('c', strtotime($blogModifiedRaw) ?: time()),
  'author' => ['@type' => 'Person', 'name' => (string)($blog['author_name'] ?? 'RCS Print Team')],
  'publisher' => [
    '@type' => 'Organization',
    'name' => (string)($settingsMap['biz_name'] ?? 'RCS Print'),
    'logo' => ['@type' => 'ImageObject', 'url' => $blogBaseUrl . '/assets/images/rcs-graphic-logo.png'],
  ],
  'mainEntityOfPage' => $blogBaseUrl . '/blog/' . rawurlencode((string)($blog['slug'] ?? '')),
]];
include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';

$settingsMap = is_array($settingsMap ?? null) ? $settingsMap : [];
$relatedBlogs = is_array($relatedBlogs ?? null) ? $relatedBlogs : [];
$blogCategories = is_array($blogCategories ?? null) ? $blogCategories : [];
$popularBlogs = is_array($popularBlogs ?? null) ? $popularBlogs : [];
$previousBlog = is_array($previousBlog ?? null) ? $previousBlog : null;
$nextBlog = is_array($nextBlog ?? null) ? $nextBlog : null;

$bizPhoneRaw = trim((string)($settingsMap['biz_phone'] ?? '+91 98765 43210'));
$bizPhone = htmlspecialchars($bizPhoneRaw, ENT_QUOTES, 'UTF-8');
$bizPhoneHref = htmlspecialchars(preg_replace('/\D+/', '', $bizPhoneRaw), ENT_QUOTES, 'UTF-8');
$bizWa = htmlspecialchars(preg_replace('/\D+/', '', (string)($settingsMap['biz_whatsapp'] ?? $bizPhoneRaw)), ENT_QUOTES, 'UTF-8');
$waText = rawurlencode('Hello RCS Print, I need help with a printing requirement.');

$titleRaw = (string)($blog['title'] ?? 'Blog article');
$title = htmlspecialchars($titleRaw, ENT_QUOTES, 'UTF-8');
$excerpt = htmlspecialchars((string)($blog['excerpt'] ?? ''), ENT_QUOTES, 'UTF-8');
$categoryRaw = trim((string)($blog['category'] ?? 'Print Tips')) ?: 'Print Tips';
$category = htmlspecialchars($categoryRaw, ENT_QUOTES, 'UTF-8');
$authorRaw = trim((string)($blog['author_name'] ?? 'RCS Print Team')) ?: 'RCS Print Team';
$author = htmlspecialchars($authorRaw, ENT_QUOTES, 'UTF-8');
$image = htmlspecialchars((string)($blog['featured_image'] ?? ''), ENT_QUOTES, 'UTF-8');
$imageAlt = htmlspecialchars((string)(($blog['image_alt'] ?? '') ?: ($blog['title'] ?? 'Blog image')), ENT_QUOTES, 'UTF-8');
$publishedAt = strtotime((string)($blog['published_at'] ?? '')) ?: time();
$published = date('M j, Y', $publishedAt);
$currentUrl = '/blog/' . rawurlencode((string)($blog['slug'] ?? ''));
$absoluteCurrentUrl = $blogBaseUrl ? ($blogBaseUrl . $currentUrl) : $currentUrl;
$shareSummaryRaw = trim(strip_tags((string)($blog['excerpt'] ?? $pageDesc ?? '')));
if ($shareSummaryRaw === '') {
  $shareSummaryRaw = 'Helpful printing tips and ideas from RCS Print.';
}
$shareSummaryRaw = preg_replace('/\s+/', ' ', $shareSummaryRaw) ?? $shareSummaryRaw;
if (function_exists('mb_substr')) {
  $shareSummaryRaw = mb_substr($shareSummaryRaw, 0, 160);
} else {
  $shareSummaryRaw = substr($shareSummaryRaw, 0, 160);
}
$shareTextRaw = trim($titleRaw . ' — ' . $shareSummaryRaw);
$shareText = htmlspecialchars($shareTextRaw, ENT_QUOTES, 'UTF-8');
$shareUrlEncoded = rawurlencode($absoluteCurrentUrl);
$shareTextEncoded = rawurlencode($shareTextRaw);

$sidebarBannerImageRaw = trim((string)($settingsMap['blog_sidebar_banner_image'] ?? ''));
$sidebarBannerUrlRaw = trim((string)($settingsMap['blog_sidebar_banner_url'] ?? ''));
$sidebarBannerAltRaw = trim((string)($settingsMap['blog_sidebar_banner_alt'] ?? ''));
$sidebarBannerActive = (int)($settingsMap['blog_sidebar_banner_active'] ?? 0) === 1 && $sidebarBannerImageRaw !== '';
$sidebarBannerNewTab = (int)($settingsMap['blog_sidebar_banner_new_tab'] ?? 0) === 1;
$sidebarBannerImage = htmlspecialchars($sidebarBannerImageRaw, ENT_QUOTES, 'UTF-8');
$sidebarBannerUrl = htmlspecialchars($sidebarBannerUrlRaw !== '' ? $sidebarBannerUrlRaw : '#', ENT_QUOTES, 'UTF-8');
$sidebarBannerAlt = htmlspecialchars($sidebarBannerAltRaw !== '' ? $sidebarBannerAltRaw : 'RCS Print banner', ENT_QUOTES, 'UTF-8');

$sanitizeBlogHtml = static function (string $html): string {
  $allowed = '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><a><blockquote><img><figure><figcaption><div><span><hr><iframe><video><source>';
  $clean = strip_tags($html, $allowed);
  $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
  $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', '$1="#"', $clean) ?? $clean;
  $clean = preg_replace_callback('/<iframe\b([^>]*)>/i', static function (array $m): string {
    $attrs = $m[1] ?? '';
    if (!preg_match('/src\s*=\s*("|\')([^"\']+)\1/i', $attrs, $srcMatch)) return '';
    $src = $srcMatch[2];
    if (!preg_match('#^https://(www\.)?(youtube\.com/embed/|player\.vimeo\.com/video/)#i', $src)) return '';
    return '<iframe src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" loading="lazy" allowfullscreen></iframe>';
  }, $clean) ?? $clean;
  return $clean;
};
$content = $sanitizeBlogHtml((string)($blog['content'] ?? ''));
$blogThumb = static function (array $item): string {
  $src = trim((string)($item['featured_image'] ?? ''));
  return $src !== '' ? $src : '/assets/images/rcs-graphic-logo.png';
};
$blogUrl = static function (?array $item): string {
  $slug = trim((string)($item['slug'] ?? ''));
  return $slug !== '' ? '/blog/' . rawurlencode($slug) : '/blogs';
};
$initials = static function (string $name): string {
  $parts = preg_split('/\s+/', trim($name));
  $letters = '';
  foreach ($parts ?: [] as $part) {
    if ($part !== '') $letters .= function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1);
    if (strlen($letters) >= 2) break;
  }
  return strtoupper($letters ?: 'RC');
};
$suggestedBlogs = array_slice($relatedBlogs, 0, 4);
?>

<main class="blog-detail-page blog-detail-pro-page">
  <section class="blog-detail-pro-hero" aria-labelledby="blogDetailTitleHero">
    <div class="blog-detail-pro-container">
      <h1 id="blogDetailTitleHero">Blog Details</h1>
      <nav class="blog-detail-pro-crumb" aria-label="Breadcrumb">
        <a href="/">Home</a><span>›</span><a href="/blogs">Blog</a><span>›</span><strong><?= $title ?></strong>
      </nav>
    </div>
  </section>

  <section class="blog-detail-pro-body">
    <div class="blog-detail-pro-container blog-detail-pro-layout">
      <article class="blog-detail-pro-article">
        <div class="blog-detail-pro-meta"><?= htmlspecialchars($published, ENT_QUOTES, 'UTF-8') ?> <span>•</span> <?= $category ?></div>
        <div class="blog-detail-pro-title-row">
          <div>
            <h2><?= $title ?></h2>
            <div class="blog-detail-pro-author">
              <span class="blog-author-avatar"><?= htmlspecialchars($initials($authorRaw), ENT_QUOTES, 'UTF-8') ?></span>
              <span><strong><?= $author ?></strong><small>Business Owner</small></span>
            </div>
          </div>
          <div class="blog-detail-share" aria-label="Share this blog" data-share-title="<?= $title ?>" data-share-text="<?= $shareText ?>" data-share-url="<?= htmlspecialchars($absoluteCurrentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <span>Share:</span>
            <a class="share-facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEncoded ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
            <a class="share-x" href="https://twitter.com/intent/tweet?url=<?= $shareUrlEncoded ?>&text=<?= $shareTextEncoded ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="fa-brands fa-x-twitter"></i></a>
            <a class="share-whatsapp" href="https://wa.me/?text=<?= rawurlencode($shareTextRaw . ' ' . $absoluteCurrentUrl) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a class="share-linkedin" href="https://www.linkedin.com/shareArticle?mini=true&url=<?= $shareUrlEncoded ?>&title=<?= rawurlencode($titleRaw) ?>&summary=<?= rawurlencode($shareSummaryRaw) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
            <button class="share-instagram" type="button" onclick="copyBlogShareText(this)" aria-label="Copy caption for Instagram"><i class="fa-brands fa-instagram"></i></button>
            <button class="share-native" type="button" onclick="shareBlogPost(this)" aria-label="More share options"><i class="fa-solid fa-share-nodes"></i></button>
          </div>
        </div>
        <?php if ($image !== ''): ?>
          <figure class="blog-detail-pro-image"><img src="<?= $image ?>" alt="<?= $imageAlt ?>" loading="eager" fetchpriority="high"></figure>
        <?php endif; ?>
        <?php if ($excerpt !== ''): ?><p class="blog-detail-pro-intro"><?= $excerpt ?></p><?php endif; ?>
        <div class="blog-detail-pro-content"><?= $content ?></div>

        <div class="blog-detail-share-block" aria-label="Share this article">
          <div><strong>Share this article</strong><p><?= $shareText ?></p></div>
          <div class="blog-detail-share" data-share-title="<?= $title ?>" data-share-text="<?= $shareText ?>" data-share-url="<?= htmlspecialchars($absoluteCurrentUrl, ENT_QUOTES, 'UTF-8') ?>">
            <a class="share-facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEncoded ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
            <a class="share-x" href="https://twitter.com/intent/tweet?url=<?= $shareUrlEncoded ?>&text=<?= $shareTextEncoded ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="fa-brands fa-x-twitter"></i></a>
            <a class="share-whatsapp" href="https://wa.me/?text=<?= rawurlencode($shareTextRaw . ' ' . $absoluteCurrentUrl) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a class="share-linkedin" href="https://www.linkedin.com/shareArticle?mini=true&url=<?= $shareUrlEncoded ?>&title=<?= rawurlencode($titleRaw) ?>&summary=<?= rawurlencode($shareSummaryRaw) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
            <button class="share-instagram" type="button" onclick="copyBlogShareText(this)" aria-label="Copy caption for Instagram"><i class="fa-brands fa-instagram"></i></button>
            <button class="share-native" type="button" onclick="shareBlogPost(this)" aria-label="More share options"><i class="fa-solid fa-share-nodes"></i></button>
          </div>
        </div>

        <?php if ($previousBlog || $nextBlog): ?>
          <nav class="blog-post-nav" aria-label="Blog post navigation">
            <?php if ($previousBlog): ?><a href="<?= htmlspecialchars($blogUrl($previousBlog), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-arrow-left"></i><span>Previous Post</span><strong><?= htmlspecialchars((string)($previousBlog['title'] ?? 'Previous post'), ENT_QUOTES, 'UTF-8') ?></strong></a><?php else: ?><span></span><?php endif; ?>
            <?php if ($nextBlog): ?><a class="next" href="<?= htmlspecialchars($blogUrl($nextBlog), ENT_QUOTES, 'UTF-8') ?>"><span>Next Post</span><strong><?= htmlspecialchars((string)($nextBlog['title'] ?? 'Next post'), ENT_QUOTES, 'UTF-8') ?></strong><i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
          </nav>
        <?php endif; ?>
      </article>

      <aside class="blog-detail-pro-sidebar" aria-label="Blog sidebar">
        <form class="blog-search-card" action="/blogs" method="get">
          <input type="search" name="search" placeholder="Search blog posts..." aria-label="Search blog posts">
          <button type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
        </form>
        <div class="blog-side-box">
          <h2>Categories</h2>
          <div class="blog-side-cats">
            <?php foreach ($blogCategories as $cat): $catName = (string)($cat['category'] ?? ''); if ($catName === '') continue; ?>
              <a class="<?= $categoryRaw === $catName ? 'active' : '' ?>" href="/blogs?category=<?= rawurlencode($catName) ?>"><span><i class="fa-solid fa-chevron-right"></i><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></span><b><?= (int)($cat['total'] ?? 0) ?></b></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="blog-side-box">
          <h2>Popular Posts</h2>
          <div class="blog-popular-list">
            <?php foreach ($popularBlogs as $post):
              $postTitle = (string)($post['title'] ?? 'Blog article');
              $postDate = !empty($post['published_at']) ? date('M j, Y', strtotime((string)$post['published_at'])) : '';
            ?>
              <a class="blog-popular-item" href="<?= htmlspecialchars($blogUrl($post), ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($blogThumb($post), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)(($post['image_alt'] ?? '') ?: $postTitle), ENT_QUOTES, 'UTF-8') ?>" loading="lazy"><span><strong><?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($postDate, ENT_QUOTES, 'UTF-8') ?></small></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php if ($sidebarBannerActive): ?>
          <a class="blog-free-design-banner has-image" href="<?= $sidebarBannerUrl ?>" <?= $sidebarBannerNewTab ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><img src="<?= $sidebarBannerImage ?>" alt="<?= $sidebarBannerAlt ?>" loading="lazy"></a>
        <?php else: ?>
          <a class="blog-free-design-banner" href="/contact"><span>Get <b>FREE Design</b><br>on Your First Order!</span><em>Get Free Design</em><i class="fa-solid fa-gift" aria-hidden="true"></i></a>
        <?php endif; ?>
      </aside>
    </div>
  </section>

  <?php if (!empty($suggestedBlogs)): ?>
    <section class="blog-detail-related-strip">
      <div class="blog-detail-pro-container">
        <div class="blog-list-head-row"><h2>Related <span>Blog</span> Posts</h2><a class="blog-read-btn" href="/blogs">View All</a></div>
        <div class="blog-list-grid">
          <?php foreach ($suggestedBlogs as $sb):
            $sbTitle = (string)($sb['title'] ?? 'Blog article');
            $sbDate = !empty($sb['published_at']) ? date('M j, Y', strtotime((string)$sb['published_at'])) : '';
          ?>
            <article class="blog-list-card blog-related-image-only">
              <a class="blog-list-card-img" href="<?= htmlspecialchars($blogUrl($sb), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($sbTitle, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($blogThumb($sb), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)(($sb['image_alt'] ?? '') ?: $sbTitle), ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></a>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <section class="blog-newsletter-section">
    <div class="blog-detail-pro-container blog-newsletter-card">
      <div class="blog-newsletter-icon"><i class="fa-regular fa-envelope" aria-hidden="true"></i></div>
      <div><h2>Stay Updated with <span>RCS Print</span></h2><p>Subscribe to get the latest printing tips, offers and product updates straight to your inbox.</p></div>
      <form action="/contact" method="get"><input type="email" name="email" placeholder="Enter your email" aria-label="Email address"><button type="submit">Subscribe</button></form>
    </div>
  </section>

  <?php $quickHelpExtraClass='blog-quick-help-section'; include INCLUDE_PATH . '/partials/quick-help.php'; ?>
</main>


<script>
async function copyBlogShareText(button) {
  const box = button?.closest('[data-share-url]');
  const text = `${box?.dataset.shareText || document.title} ${box?.dataset.shareUrl || location.href}`.trim();
  try {
    await navigator.clipboard.writeText(text);
    button.classList.add('copied');
    button.setAttribute('aria-label', 'Share text copied');
    setTimeout(() => button.classList.remove('copied'), 1400);
  } catch (e) {
    window.prompt('Copy this share text:', text);
  }
}
async function shareBlogPost(button) {
  const box = button?.closest('[data-share-url]');
  const title = box?.dataset.shareTitle || document.title;
  const text = box?.dataset.shareText || title;
  const url = box?.dataset.shareUrl || location.href;
  if (navigator.share) {
    try { await navigator.share({ title, text, url }); return; } catch (e) { if (e.name === 'AbortError') return; }
  }
  await copyBlogShareText(button);
}
</script>

<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>
