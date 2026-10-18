<?php
require_once __DIR__.'/includes/bootstrap.php';
$s=db()->prepare("SELECT p.*,u.full_name FROM club_posts p JOIN club_users u ON u.id=p.author_user_id WHERE p.slug=? AND p.status='published'");$s->execute([(string)($_GET['slug']??'')]);$post=$s->fetch();
if(!$post){http_response_code(404);seo_set(['title'=>'Post unavailable']);require __DIR__.'/includes/header.php';echo '<section class="section container"><h1>Post unavailable</h1><a href="'.e(site_url('/news.php')).'">Club news</a></section>';require __DIR__.'/includes/footer.php';exit;}
seo_set(['title'=>$post['title'],'description'=>truncate_text($post['content'])]);require __DIR__.'/includes/header.php';
?>
<article class="section"><div class="container" style="max-width:860px"><p class="eyebrow">FROM YOUR CLUB COMMUNITY</p><h1><?= e($post['title']) ?></h1><p class="text-muted"><?= e(format_date($post['published_at'])) ?> · <?= e($post['full_name']) ?></p><div class="rich-content"><?= sanitize_html($post['content']) ?></div><?php club_content_attachments('post',(int)$post['id']); ?><?php if(club_content_editable($post)): ?><a class="btn btn--secondary" href="<?= e(site_url('/members/post-edit.php?id='.$post['id'])) ?>">Edit post</a><?php endif ?></div></article>
<?php require __DIR__.'/includes/footer.php'; ?>
