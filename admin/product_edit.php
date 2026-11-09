<?php
// Admin: add or edit a product (with optional photo upload)
require_once __DIR__ . '/includes/bootstrap.php';
$admin = a_require_admin($pdo);

$id      = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$product = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $product = $st->fetch();
    if (!$product) {
        a_flash('danger', 'That product was not found.');
        a_redirect('products.php');
    }
}

$errors = [];
$f = [
    'name'        => $product['name']        ?? '',
    'category'    => $product['category']    ?? A_CATEGORIES[0],
    'description' => $product['description'] ?? '',
    'price'       => isset($product['price']) ? number_format((float)$product['price'], 2, '.', '') : '',
    'emoji'       => $product['emoji']       ?? '🧁',
    'badge'       => $product['badge']       ?? '',
    'stock'       => (string)($product['stock'] ?? 0),
    'is_featured' => (int)($product['is_featured'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    a_csrf_verify();
    $f['name']        = a_str('name');
    $f['category']    = a_str('category');
    $f['description'] = a_str('description');
    $f['price']       = a_str('price');
    $f['emoji']       = a_str('emoji');
    $f['badge']       = a_str('badge');
    $f['stock']       = a_str('stock');
    $f['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $removeImage      = isset($_POST['remove_image']);

    if (mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 120)               $errors[] = 'Name must be 2 to 120 characters.';
    if (!in_array($f['category'], A_CATEGORIES, true))                           $errors[] = 'Please choose a category.';
    if ($f['description'] === '' || mb_strlen($f['description']) > 255)         $errors[] = 'Description is required (max 255 characters).';
    if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $f['price']) || (float)$f['price'] <= 0) $errors[] = 'Price must be a positive amount like 499 or 499.50.';
    if ($f['emoji'] === '' || mb_strlen($f['emoji']) > 16)                       $errors[] = 'Emoji is required (max 16 characters). It shows when there is no photo.';
    if (mb_strlen($f['badge']) > 30)                                             $errors[] = 'Badge can be at most 30 characters.';
    if (!ctype_digit($f['stock']) || (int)$f['stock'] > 9999)                    $errors[] = 'Stock must be a whole number from 0 to 9999.';

    /* ---- photo upload ---- */
    $newImage = null;
    $file = $_FILES['image'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The photo could not be uploaded (error code ' . (int)$file['error'] . '). Max size is 2 MB.';
        } elseif ($file['size'] > A_IMG_MAX_BYTES) {
            $errors[] = 'The photo is larger than 2 MB.';
        } else {
            // Trust the file's real contents, not its name or the browser-supplied type
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($exts[$mime]) || @getimagesize($file['tmp_name']) === false) {
                $errors[] = 'The photo must be a JPG, PNG or WebP image.';
            } elseif (!is_dir(A_IMG_DIR) || !is_writable(A_IMG_DIR)) {
                $errors[] = 'The assets/images folder is missing or not writable by the web server.';
            } else {
                $newImage = 'p_' . bin2hex(random_bytes(8)) . '.' . $exts[$mime];
            }
        }
    }

    if (!$errors) {
        if ($newImage !== null && !move_uploaded_file($file['tmp_name'], A_IMG_DIR . '/' . $newImage)) {
            $errors[] = 'Could not save the uploaded photo.';
        }
    }

    if (!$errors) {
        $oldImage = $product['image'] ?? null;
        $image    = $oldImage;
        if ($newImage !== null)  { $image = $newImage; }
        elseif ($removeImage)    { $image = null; }

        $vals = [$f['name'], $f['category'], $f['description'], $f['price'], $f['emoji'],
                 $f['badge'] !== '' ? $f['badge'] : null, (int)$f['stock'], $f['is_featured'], $image];
        try {
            if ($id) {
                $pdo->prepare('UPDATE products SET name=?, category=?, description=?, price=?, emoji=?, badge=?, stock=?, is_featured=?, image=? WHERE id=?')
                    ->execute(array_merge($vals, [$id]));
            } else {
                $pdo->prepare('INSERT INTO products (name, category, description, price, emoji, badge, stock, is_featured, image) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute($vals);
            }
            // Remove the replaced photo, but only if this admin panel created it
            if ($oldImage && $oldImage !== $image && preg_match('/^p_[a-f0-9]{16}\.(jpg|png|webp)$/', $oldImage)) {
                @unlink(A_IMG_DIR . '/' . $oldImage);
            }
            a_flash('success', $id ? 'Product updated.' : 'Product added.');
            a_redirect('products.php');
        } catch (PDOException $ex) {
            if ($newImage !== null) { @unlink(A_IMG_DIR . '/' . $newImage); }
            $errors[] = 'Could not save the product. Please try again.';
        }
    }
}

a_header($id ? 'Edit product' : 'Add product', 'products', $admin);
?>
<div class="mb-3"><a href="products.php">&larr; All products</a></div>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="card"><div class="card-body p-4">
    <h1 class="h4 mb-4"><?= $id ? 'Edit product' : 'Add product' ?></h1>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= a_e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="product_edit.php<?= $id ? '?id=' . $id : '' ?>">
        <?= a_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="name">Name</label>
                <input class="form-control" id="name" name="name" required maxlength="120" value="<?= a_e($f['name']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category">Category</label>
                <select class="form-select" id="category" name="category" required>
                    <?php foreach (A_CATEGORIES as $c): ?><option <?= $f['category'] === $c ? 'selected' : '' ?>><?= a_e($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2" required maxlength="255"><?= a_e($f['description']) ?></textarea>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="price">Price (₹)</label>
                <input class="form-control" id="price" name="price" required inputmode="decimal" placeholder="499.00" value="<?= a_e($f['price']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="stock">Stock</label>
                <input class="form-control" type="number" id="stock" name="stock" required min="0" max="9999" value="<?= a_e($f['stock']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="emoji">Emoji</label>
                <input class="form-control" id="emoji" name="emoji" required maxlength="16" value="<?= a_e($f['emoji']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="badge">Badge <span class="text-muted small">(optional)</span></label>
                <input class="form-control" id="badge" name="badge" maxlength="30" placeholder="Bestseller" value="<?= a_e($f['badge']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="image">Photo <span class="text-muted small">(optional · JPG, PNG or WebP · max 2 MB)</span></label>
                <?php if (!empty($product['image'])): ?>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img class="thumb" style="width:72px;height:72px" src="../assets/images/<?= a_e($product['image']) ?>" alt="">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image">
                            <label class="form-check-label" for="remove_image">Remove current photo</label>
                        </div>
                    </div>
                <?php endif; ?>
                <input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" <?= $f['is_featured'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_featured">Feature on the home page (“Customer favourites”)</label>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-rose" type="submit"><?= $id ? 'Save changes' : 'Add product' ?></button>
            <a class="btn btn-outline-secondary" href="products.php">Cancel</a>
        </div>
    </form>
</div></div>
</div></div>
<?php a_footer(); ?>
