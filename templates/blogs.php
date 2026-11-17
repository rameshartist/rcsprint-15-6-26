<?php
/**
 * blogs.php — Public Blogs Listing
 * Route: /blogs
 */
$pageTitle = 'Our Blog — RCS Graphic';
$pageDesc  = 'Read printing tips, design ideas, branding insights and bulk order guides from RCS Print.';

$blogs = is_array($blogs ?? null) ? $blogs : [];
$blogCategories = is_array($blogCategories ?? null) ? $blogCategories : [];
$popularBlogs = is_array($popularBlogs ?? null) ? $popularBlogs : [];
$featuredBlog = is_array($featuredBlog ?? null) ? $featuredBlog : ($blogs[0] ?? null);
$blogSearch = trim((string)($blogSearch ?? ''));
$blogCategory = trim((string)($blogCategory ?? ''));
$blogPage = max(1, (int)($blogPage ?? 1));
$blogTotalPages = max(1, (int)($blogTotalPages ?? 1));
$blogTotal = max(0, (int)($blogTotal ?? count($blogs)));
$settingsMap = is_array($settingsMap ?? null) ? $settingsMap : [];

$blogDate = static function ($value): string {
  $time = strtotime((string)$value);
  return $time ? date('M j, Y', $time) : date('M j, Y');
};
$blogUrl = static function (array $blog): string {
  $slug = trim((string)($blog['slug'] ?? ''));
  return $slug !== '' ? '/blog/' . rawurlencode($slug) : '/blogs';
};
$blogImage = static function (array $blog): string {
  $src = trim((string)($blog['featured_image'] ?? ''));
  return $src !== '' ? $src : '/assets/images/rcs-graphic-logo.png';
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
$pageHref = static function (int $page) use ($blogSearch, $blogCategory): string {
  $query = [];
  if ($blogSearch !== '') $query['search'] = $blogSearch;
  if ($blogCategory !== '') $query['category'] = $blogCategory;
  if ($page > 1) $query['page'] = $page;
  return '/blogs' . ($query ? '?' . http_build_query($query) : '');
};
$filterHref = static function (string $category = '') use ($blogSearch): string {
  $query = [];
  if ($blogSearch !== '') $query['search'] = $blogSearch;
  if ($category !== '') $query['category'] = $category;
  return '/blogs' . ($query ? '?' . http_build_query($query) : '');
};

$bizName = htmlspecialchars((string)($settingsMap['biz_name'] ?? 'RCS PRINT'), ENT_QUOTES, 'UTF-8');
$bizPhoneRaw = trim((string)($settingsMap['biz_phone'] ?? '+91 98765 43210'));
$bizPhone = htmlspecialchars($bizPhoneRaw, ENT_QUOTES, 'UTF-8');
$bizPhoneHref = htmlspecialchars(preg_replace('/\D+/', '', $bizPhoneRaw), ENT_QUOTES, 'UTF-8');
$bizWa = htmlspecialchars(preg_replace('/\D+/', '', (string)($settingsMap['biz_whatsapp'] ?? $bizPhoneRaw)), ENT_QUOTES, 'UTF-8');
$waText = rawurlencode('Hello RCS Print, I need help with a printing requirement.');
$sidebarBannerImageRaw = trim((string)($settingsMap['blog_sidebar_banner_image'] ?? ''));
$sidebarBannerUrlRaw = trim((string)($settingsMap['blog_sidebar_banner_url'] ?? ''));
$sidebarBannerAltRaw = trim((string)($settingsMap['blog_sidebar_banner_alt'] ?? ''));
$sidebarBannerActive = (int)($settingsMap['blog_sidebar_banner_active'] ?? 0) === 1 && $sidebarBannerImageRaw !== '';
$sidebarBannerNewTab = (int)($settingsMap['blog_sidebar_banner_new_tab'] ?? 0) === 1;

include INCLUDE_PATH . '/partials/head.php';
include INCLUDE_PATH . '/partials/header.php';
?>
<main class="blog-list-page">
  <?php
  $pageHero = [
    'key' => 'blogs',
    'title' => 'Our Blog',
    'subtitle' => 'Insights, tips and inspiration about printing, branding and growing your business.',
    'eyebrow' => 'Printing Insights',
    'breadcrumbs' => [
      ['label' => 'Home', 'url' => '/'],
      ['label' => 'Blog', 'url' => null],
    ],
    'fallback_image' => '/assets/images/sample-products/flyers/flyers-1.svg',
  ];
  include INCLUDE_PATH . '/partials/page-hero.php';
  ?>

  <section class="blog-list-main-section">
    <div class="blog-list-container blog-list-layout">
      <div class="blog-list-content">
        <?php if ($featuredBlog):
          $featuredTitle = trim((string)($featuredBlog['title'] ?? 'Featured Blog'));
          $featuredUrl = $blogUrl($featuredBlog);
          $featuredImage = $blogImage($featuredBlog);
          $featuredAlt = trim((string)($featuredBlog['image_alt'] ?? '')) ?: $featuredTitle;
          $featuredCategory = trim((string)($featuredBlog['category'] ?? 'Print Tips')) ?: 'Print Tips';
          $featuredAuthor = trim((string)($featuredBlog['author_name'] ?? 'RCS Print Team')) ?: 'RCS Print Team';
        ?>
          <article class="blog-feature-card">
            <a class="blog-feature-image" href="<?= htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= htmlspecialchars($featuredImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($featuredAlt, ENT_QUOTES, 'UTF-8') ?>" loading="eager" fetchpriority="high">
              <span>Featured</span>
            </a>
            <div class="blog-feature-copy">
              <div class="blog-card-meta"><?= htmlspecialchars($blogDate($featuredBlog['published_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <span>•</span> <?= htmlspecialchars($featuredCategory, ENT_QUOTES, 'UTF-8') ?></div>
              <h2><a href="<?= htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($featuredTitle, ENT_QUOTES, 'UTF-8') ?></a></h2>
              <?php if (!empty($featuredBlog['excerpt'])): ?><p><?= htmlspecialchars((string)$featuredBlog['excerpt'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
              <div class="blog-author-row">
                <span class="blog-author-avatar"><?= htmlspecialchars($initials($featuredAuthor), ENT_QUOTES, 'UTF-8') ?></span>
                <span><strong><?= htmlspecialchars($featuredAuthor, ENT_QUOTES, 'UTF-8') ?></strong><small>RCS Print Team</small></span>
                <a class="blog-read-btn" href="<?= htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8') ?>">Read More <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </article>
        <?php endif; ?>

        <div class="blog-list-head-row">
          <h2>All <span>Blog</span> Posts</h2>
          <div class="blog-filter-chips" aria-label="Blog category filters">
            <a class="<?= $blogCategory === '' ? 'active' : '' ?>" href="<?= htmlspecialchars($filterHref(), ENT_QUOTES, 'UTF-8') ?>">All</a>
            <?php foreach (array_slice($blogCategories, 0, 6) as $cat):
              $catName = (string)($cat['category'] ?? '');
              if ($catName === '') continue;
            ?>
              <a class="<?= $blogCategory === $catName ? 'active' : '' ?>" href="<?= htmlspecialchars($filterHref($catName), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if (empty($blogs)): ?>
          <div class="blog-empty-state"><strong>No blogs found</strong><span>Try a different search or category filter.</span><a href="/blogs">Reset filters</a></div>
        <?php else: ?>
          <div class="blog-list-grid" role="list">
            <?php foreach ($blogs as $blog):
              $title = trim((string)($blog['title'] ?? 'Blog'));
              $url = $blogUrl($blog);
              $image = $blogImage($blog);
              $alt = trim((string)($blog['image_alt'] ?? '')) ?: $title;
              $category = trim((string)($blog['category'] ?? 'Print Tips')) ?: 'Print Tips';
              $author = trim((string)($blog['author_name'] ?? 'RCS Print Team')) ?: 'RCS Print Team';
            ?>
              <article class="blog-list-card" role="listitem">
                <a class="blog-list-card-img" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></a>
                <div class="blog-list-card-body">
                  <div class="blog-card-meta"><?= htmlspecialchars($blogDate($blog['published_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <span>•</span> <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></div>
                  <h3><a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></a></h3>
                  <?php if (!empty($blog['excerpt'])): ?><p><?= htmlspecialchars((string)$blog['excerpt'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                  <div class="blog-card-foot"><span class="blog-author-avatar small"><?= htmlspecialchars($initials($author), ENT_QUOTES, 'UTF-8') ?></span><span><strong><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8') ?></strong><small>Author</small></span><a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">Read More <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($blogTotalPages > 1): ?>
          <nav class="blog-pagination" aria-label="Blog pagination">
            <?php if ($blogPage > 1): ?><a href="<?= htmlspecialchars($pageHref($blogPage - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous page">‹</a><?php endif; ?>
            <?php
              $pages = array_unique(array_filter([1, $blogPage - 1, $blogPage, $blogPage + 1, $blogTotalPages], static fn($p) => $p >= 1 && $p <= $blogTotalPages));
              $last = 0;
              foreach ($pages as $p):
                if ($last && $p > $last + 1) echo '<span>…</span>';
                $last = $p;
            ?>
              <a class="<?= $p === $blogPage ? 'active' : '' ?>" href="<?= htmlspecialchars($pageHref((int)$p), ENT_QUOTES, 'UTF-8') ?>"><?= (int)$p ?></a>
            <?php endforeach; ?>
            <?php if ($blogPage < $blogTotalPages): ?><a href="<?= htmlspecialchars($pageHref($blogPage + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next page">›</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>

      <aside class="blog-list-sidebar" aria-label="Blog sidebar">
        <form class="blog-search-card" action="/blogs" method="get">
          <?php if ($blogCategory !== ''): ?><input type="hidden" name="category" value="<?= htmlspecialchars($blogCategory, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
          <input type="search" name="search" value="<?= htmlspecialchars($blogSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search blog posts..." aria-label="Search blog posts">
          <button type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
        </form>

        <div class="blog-side-box">
          <h2>Categories</h2>
          <div class="blog-side-cats">
            <?php foreach ($blogCategories as $cat):
              $catName = (string)($cat['category'] ?? '');
              if ($catName === '') continue;
            ?>
              <a class="<?= $blogCategory === $catName ? 'active' : '' ?>" href="<?= htmlspecialchars($filterHref($catName), ENT_QUOTES, 'UTF-8') ?>"><span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?></span><b><?= (int)($cat['total'] ?? 0) ?></b></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="blog-side-box">
          <h2>Popular Posts</h2>
          <div class="blog-popular-list">
            <?php foreach ($popularBlogs as $post):
              $postTitle = trim((string)($post['title'] ?? 'Blog'));
              $postUrl = $blogUrl($post);
              $postImg = $blogImage($post);
              $postAlt = trim((string)($post['image_alt'] ?? '')) ?: $postTitle;
            ?>
              <a class="blog-popular-item" href="<?= htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($postImg, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($postAlt, ENT_QUOTES, 'UTF-8') ?>" loading="lazy"><span><strong><?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($blogDate($post['published_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></span></a>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($sidebarBannerActive): ?>
          <a class="blog-free-design-banner has-image" href="<?= htmlspecialchars($sidebarBannerUrlRaw !== '' ? $sidebarBannerUrlRaw : '/contact', ENT_QUOTES, 'UTF-8') ?>" <?= $sidebarBannerNewTab ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><img src="<?= htmlspecialchars($sidebarBannerImageRaw, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($sidebarBannerAltRaw ?: 'RCS Print offer', ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></a>
        <?php else: ?>
          <a class="blog-free-design-banner" href="/contact"><span>Get <b>FREE Design</b><br>on Your First Order!</span><em>Get Free Design</em><i class="fa-solid fa-gift" aria-hidden="true"></i></a>
        <?php endif; ?>
      </aside>
    </div>
  </section>

  <section class="blog-newsletter-section">
    <div class="blog-list-container blog-newsletter-card">
      <div class="blog-newsletter-icon"><i class="fa-regular fa-envelope" aria-hidden="true"></i></div>
      <div><h2>Stay Updated with <span>RCS Print</span></h2><p>Subscribe to get the latest printing tips, offers and product updates straight to your inbox.</p></div>
      <form action="/contact" method="get"><input type="email" name="email" placeholder="Enter your email" aria-label="Email address"><button type="submit">Subscribe</button></form>
    </div>
  </section>

  <?php $quickHelpExtraClass='blog-quick-help-section'; include INCLUDE_PATH . '/partials/quick-help.php'; ?>
</main>
<?php include INCLUDE_PATH . '/partials/site-footer.php'; ?>
<?php include INCLUDE_PATH . '/partials/footer.php'; ?>
