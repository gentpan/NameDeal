<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
require_admin();
require __DIR__ . '/_layout.php';

$content = read_json('content.json', []);

$defaultConfig = [
    'hero' => [
        'mode' => 'text',
        'text' => [
            'main_url' => 'https://prime.ls',
            'label' => '推荐域名',
            'title' => 'PRIME.LS',
            'description' => '高端品牌主域，适合旗舰业务与品牌升级',
            'link_text' => '立即访问 →',
            'side' => [
                ['title' => 'DESIGN.LS', 'description' => '创意与视觉品牌', 'url' => 'https://design.ls'],
                ['title' => 'CLOUD.LS', 'description' => '云服务优选域名', 'url' => 'https://cloud.ls'],
                ['title' => 'AI.LS', 'description' => '双字符 AI 精品', 'url' => 'https://ai.ls'],
            ],
        ],
        'image' => [
            'url' => '',
            'alt' => '推荐域名',
            'link' => '#',
        ],
    ],
    'welcome' => [
        'mode' => 'text',
        'text' => [
            'slides' => [
                ['tag' => '品牌主场景', 'title' => 'BRAND.LS', 'description' => '适合企业主站与统一品牌门户', 'url' => 'https://brand.ls'],
                ['tag' => '创业项目', 'title' => 'STARTUP.LS', 'description' => '简洁易记，适合新产品冷启动', 'url' => 'https://startup.ls'],
                ['tag' => '投资并购', 'title' => 'CAPITAL.LS', 'description' => '金融资本类域名组合方案', 'url' => 'https://capital.ls'],
            ],
            'logos' => ['DOMAIN.LS', 'PRIME.LS', 'AI.LS', 'CLOUD.LS', 'DESIGN.LS', 'BRAND.LS'],
        ],
        'image' => [
            'url' => '',
            'alt' => '品牌展示',
            'link' => '#',
        ],
    ],
];

$currentConfig = $content['home_welcome'] ?? [];
$config = array_replace_recursive($defaultConfig, is_array($currentConfig) ? $currentConfig : []);
$message = '';
$error = '';

function upload_welcome_image(string $field, ?string &$error = null): ?string
{
    $maxFileSize = 2 * 1024 * 1024; // 2 MB

    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $errorMap = [
            UPLOAD_ERR_INI_SIZE => '上传文件超过服务器大小限制',
            UPLOAD_ERR_FORM_SIZE => '上传文件超过表单大小限制',
            UPLOAD_ERR_PARTIAL => '文件仅部分上传，请重试',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '服务器写入文件失败',
            UPLOAD_ERR_EXTENSION => '上传被服务器扩展中断',
        ];
        $error = $errorMap[$uploadError] ?? '文件上传失败';
        return null;
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $error = '上传来源无效，请重试';
        return null;
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        $error = '上传文件为空';
        return null;
    }
    if ($size > $maxFileSize) {
        $error = '图片大小不能超过 2MB';
        return null;
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        $error = '图片格式仅支持 JPG / PNG / WEBP / GIF';
        return null;
    }

    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = (string)finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
        }
    }
    if ($mime === '' && function_exists('getimagesize')) {
        $imageInfo = @getimagesize($tmpPath);
        if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
            $mime = (string)$imageInfo['mime'];
        }
    }
    if ($mime === '' || !in_array($mime, $allowedMimes, true)) {
        $error = '文件内容不是有效图片';
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/welcome';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $error = '创建上传目录失败';
            return null;
        }
    }

    $filename = 'welcome_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $dest)) {
        $error = '保存图片失败，请检查目录权限';
        return null;
    }

    return '/uploads/welcome/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $heroMode = (string)($_POST['hero_mode'] ?? 'text');
    $welcomeMode = (string)($_POST['welcome_mode'] ?? 'text');

    if (!in_array($heroMode, ['text', 'image'], true)) {
        $heroMode = 'text';
    }
    if (!in_array($welcomeMode, ['text', 'image'], true)) {
        $welcomeMode = 'text';
    }

    $heroSide = [];
    for ($i = 0; $i < 3; $i++) {
        $heroSide[] = [
            'title' => trim((string)($_POST['hero_side_title'][$i] ?? '')),
            'description' => trim((string)($_POST['hero_side_desc'][$i] ?? '')),
            'url' => trim((string)($_POST['hero_side_url'][$i] ?? '#')),
        ];
    }

    $welcomeSlides = [];
    for ($i = 0; $i < 3; $i++) {
        $welcomeSlides[] = [
            'tag' => trim((string)($_POST['welcome_slide_tag'][$i] ?? '')),
            'title' => trim((string)($_POST['welcome_slide_title'][$i] ?? '')),
            'description' => trim((string)($_POST['welcome_slide_desc'][$i] ?? '')),
            'url' => trim((string)($_POST['welcome_slide_url'][$i] ?? '#')),
        ];
    }

    $welcomeLogos = [];
    for ($i = 0; $i < 6; $i++) {
        $welcomeLogos[] = trim((string)($_POST['welcome_logo'][$i] ?? ''));
    }

    $newConfig = [
        'hero' => [
            'mode' => $heroMode,
            'text' => [
                'main_url' => trim((string)($_POST['hero_main_url'] ?? '#')),
                'label' => trim((string)($_POST['hero_label'] ?? '推荐域名')),
                'title' => trim((string)($_POST['hero_title'] ?? '')),
                'description' => trim((string)($_POST['hero_description'] ?? '')),
                'link_text' => trim((string)($_POST['hero_link_text'] ?? '立即访问 →')),
                'side' => $heroSide,
            ],
            'image' => [
                'url' => trim((string)($_POST['hero_image_url'] ?? ($config['hero']['image']['url'] ?? ''))),
                'alt' => trim((string)($_POST['hero_image_alt'] ?? '推荐域名')),
                'link' => trim((string)($_POST['hero_image_link'] ?? '#')),
            ],
        ],
        'welcome' => [
            'mode' => $welcomeMode,
            'text' => [
                'slides' => $welcomeSlides,
                'logos' => $welcomeLogos,
            ],
            'image' => [
                'url' => trim((string)($_POST['welcome_image_url'] ?? ($config['welcome']['image']['url'] ?? ''))),
                'alt' => trim((string)($_POST['welcome_image_alt'] ?? '品牌展示')),
                'link' => trim((string)($_POST['welcome_image_link'] ?? '#')),
            ],
        ],
    ];

    $uploadErrors = [];

    $heroUploadError = null;
    $heroUploaded = upload_welcome_image('hero_image_file', $heroUploadError);
    if ($heroUploaded !== null) {
        $newConfig['hero']['image']['url'] = $heroUploaded;
    } elseif ($heroUploadError !== null) {
        $uploadErrors[] = '左侧推荐图上传失败：' . $heroUploadError;
    }

    $welcomeUploadError = null;
    $welcomeUploaded = upload_welcome_image('welcome_image_file', $welcomeUploadError);
    if ($welcomeUploaded !== null) {
        $newConfig['welcome']['image']['url'] = $welcomeUploaded;
    } elseif ($welcomeUploadError !== null) {
        $uploadErrors[] = '右侧 Welcome 图上传失败：' . $welcomeUploadError;
    }

    $content['home_welcome'] = $newConfig;
    write_json('content.json', $content);

    $config = $newConfig;
    $message = $uploadErrors ? '配置已保存，但有图片未上传成功' : '首页 Welcome 配置已保存';
    $error = implode('；', $uploadErrors);
}

admin_header('首页 Welcome 设置');
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="admin-card mb-4">
    <h5 class="mb-3">图片尺寸建议</h5>
    <ul class="mb-0">
        <li>左侧推荐区（Hero）建议：1200 × 760（16:10）</li>
        <li>右侧 Welcome 区建议：1200 × 760（16:10）</li>
        <li>支持 JPG / PNG / WEBP / GIF，单张不超过 2MB，前台默认自适应裁切显示（object-fit: cover）</li>
    </ul>
</div>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_input(); ?>
    <div class="admin-card mb-4">
        <h5 class="mb-3">区域一：推荐域名（左侧）</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">显示模式</label>
                <select class="form-select" name="hero_mode">
                    <option value="text" <?php echo ($config['hero']['mode'] === 'text') ? 'selected' : ''; ?>>文字模式</option>
                    <option value="image" <?php echo ($config['hero']['mode'] === 'image') ? 'selected' : ''; ?>>图片模式</option>
                </select>
            </div>
        </div>

        <h6>文字模式内容</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><input class="form-control" name="hero_label" placeholder="标签" value="<?php echo h($config['hero']['text']['label']); ?>"></div>
            <div class="col-md-4"><input class="form-control" name="hero_title" placeholder="标题" value="<?php echo h($config['hero']['text']['title']); ?>"></div>
            <div class="col-md-4"><input class="form-control" name="hero_link_text" placeholder="按钮文字" value="<?php echo h($config['hero']['text']['link_text']); ?>"></div>
            <div class="col-md-12"><input class="form-control" name="hero_main_url" placeholder="主链接" value="<?php echo h($config['hero']['text']['main_url']); ?>"></div>
            <div class="col-md-12"><textarea class="form-control" name="hero_description" rows="2" placeholder="描述"><?php echo h($config['hero']['text']['description']); ?></textarea></div>
        </div>

        <h6>右侧三条推荐</h6>
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="row g-2 mb-2">
                <div class="col-md-3"><input class="form-control" name="hero_side_title[]" placeholder="标题" value="<?php echo h($config['hero']['text']['side'][$i]['title'] ?? ''); ?>"></div>
                <div class="col-md-5"><input class="form-control" name="hero_side_desc[]" placeholder="描述" value="<?php echo h($config['hero']['text']['side'][$i]['description'] ?? ''); ?>"></div>
                <div class="col-md-4"><input class="form-control" name="hero_side_url[]" placeholder="链接" value="<?php echo h($config['hero']['text']['side'][$i]['url'] ?? '#'); ?>"></div>
            </div>
        <?php endfor; ?>

        <hr>
        <h6>图片模式内容</h6>
        <div class="row g-3">
            <div class="col-md-6"><input class="form-control" name="hero_image_url" placeholder="图片 URL（可选）" value="<?php echo h($config['hero']['image']['url']); ?>"></div>
            <div class="col-md-6"><input class="form-control" type="file" name="hero_image_file" accept="image/*"></div>
            <div class="col-md-6"><input class="form-control" name="hero_image_link" placeholder="点击跳转链接" value="<?php echo h($config['hero']['image']['link']); ?>"></div>
            <div class="col-md-6"><input class="form-control" name="hero_image_alt" placeholder="图片说明" value="<?php echo h($config['hero']['image']['alt']); ?>"></div>
            <?php if (!empty($config['hero']['image']['url'])): ?>
                <div class="col-12">
                    <div class="small text-body-secondary mb-2">当前图片预览</div>
                    <img src="<?php echo h($config['hero']['image']['url']); ?>" alt="<?php echo h($config['hero']['image']['alt'] ?? 'Hero 图片'); ?>" class="img-fluid border" style="width:100%;max-height:220px;object-fit:cover;">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card mb-4">
        <h5 class="mb-3">区域二：Welcome 展示（右侧）</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">显示模式</label>
                <select class="form-select" name="welcome_mode">
                    <option value="text" <?php echo ($config['welcome']['mode'] === 'text') ? 'selected' : ''; ?>>文字模式</option>
                    <option value="image" <?php echo ($config['welcome']['mode'] === 'image') ? 'selected' : ''; ?>>图片模式</option>
                </select>
            </div>
        </div>

        <h6>文字轮播（3条）</h6>
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="row g-2 mb-2">
                <div class="col-md-2"><input class="form-control" name="welcome_slide_tag[]" placeholder="标签" value="<?php echo h($config['welcome']['text']['slides'][$i]['tag'] ?? ''); ?>"></div>
                <div class="col-md-3"><input class="form-control" name="welcome_slide_title[]" placeholder="标题" value="<?php echo h($config['welcome']['text']['slides'][$i]['title'] ?? ''); ?>"></div>
                <div class="col-md-4"><input class="form-control" name="welcome_slide_desc[]" placeholder="描述" value="<?php echo h($config['welcome']['text']['slides'][$i]['description'] ?? ''); ?>"></div>
                <div class="col-md-3"><input class="form-control" name="welcome_slide_url[]" placeholder="链接" value="<?php echo h($config['welcome']['text']['slides'][$i]['url'] ?? '#'); ?>"></div>
            </div>
        <?php endfor; ?>

        <h6 class="mt-3">Logo 文字（6个）</h6>
        <div class="row g-2 mb-3">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="col-md-4"><input class="form-control" name="welcome_logo[]" placeholder="Logo文字" value="<?php echo h($config['welcome']['text']['logos'][$i] ?? ''); ?>"></div>
            <?php endfor; ?>
        </div>

        <hr>
        <h6>图片模式内容</h6>
        <div class="row g-3">
            <div class="col-md-6"><input class="form-control" name="welcome_image_url" placeholder="图片 URL（可选）" value="<?php echo h($config['welcome']['image']['url']); ?>"></div>
            <div class="col-md-6"><input class="form-control" type="file" name="welcome_image_file" accept="image/*"></div>
            <div class="col-md-6"><input class="form-control" name="welcome_image_link" placeholder="点击跳转链接" value="<?php echo h($config['welcome']['image']['link']); ?>"></div>
            <div class="col-md-6"><input class="form-control" name="welcome_image_alt" placeholder="图片说明" value="<?php echo h($config['welcome']['image']['alt']); ?>"></div>
            <?php if (!empty($config['welcome']['image']['url'])): ?>
                <div class="col-12">
                    <div class="small text-body-secondary mb-2">当前图片预览</div>
                    <img src="<?php echo h($config['welcome']['image']['url']); ?>" alt="<?php echo h($config['welcome']['image']['alt'] ?? 'Welcome 图片'); ?>" class="img-fluid border" style="width:100%;max-height:220px;object-fit:cover;">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">保存配置</button>
        <a class="btn btn-outline-secondary" href="/">查看前台</a>
    </div>
</form>

<?php
admin_footer();
