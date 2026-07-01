<?php
/* ============================================================
   BOOT — Start session and load dependencies
============================================================ */
session_start();
include('dbcon.php');

/* ============================================================
   GUARD — Reject requests that are missing a user_id param
============================================================ */
if (empty($_GET['user_id'])) {
    die("<script>
            alert('Missing user_id');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

/* ============================================================
   FETCH USER
============================================================ */
$current_user_id = $_GET['user_id'];

$query = "SELECT * FROM USER WHERE user_id = ?";
$stmt = $condb->prepare($query);
$stmt->bind_param("s", $current_user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows != 1) {
    die("<script>
            alert('User not found');
            window.location.href='dashboard/dist/index.php';
         </script>");
}

$user = $result->fetch_assoc();
$user_picture = $user['profilepicture'] ?? '';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Profile — CYCOM E-Proposal</title>

    <link href="/Presento/assets/img/favicon.png" rel="icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/Presento/dashboard/dist/assets/css/mainc1.css">
    <link rel="stylesheet" href="/Presento/assets/css/style.css">

    <!-- Cropper.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">

    <style>
        /* ---------- crop modal overlay ---------- */
        #crop-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.75);
            align-items: center;
            justify-content: center;
        }
        #crop-modal.active {
            display: flex;
        }
        #crop-modal-inner {
            background: #3c0e26;
            border: 2px solid #fff;
            border-radius: 10px;
            padding: 1.5rem;
            width: min(520px, 95vw);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        #crop-modal-inner h5 {
            color: #fff;
            font-weight: 700;
            margin: 0;
            font-size: 1rem;
        }
        /* constrain the cropper area */
        #crop-image-wrap {
            width: 100%;
            max-height: 340px;
            overflow: hidden;
            border-radius: 6px;
            background: #000;
        }
        #crop-image-wrap img {
            display: block;
            max-width: 100%;
        }
        #crop-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-crop-cancel {
            padding: 8px 18px;
            border-radius: 4px;
            background: transparent;
            border: 1px solid #fff;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-crop-apply {
            padding: 8px 18px;
            border-radius: 4px;
            background: #fff;
            border: none;
            color: #491231;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
        }

        /* ---------- thumbnail preview inside form ---------- */
        #crop-thumb-wrap {
            display: none;
            margin-top: 0.6rem;
            position: relative;
            width: fit-content;
        }
        #crop-thumb {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
        }
        #crop-thumb-remove {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #491231;
            border: 2px solid #fff;
            color: #fff;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.7rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        #crop-hint {
            font-size: 0.8rem;
            color: #d8c4d0;
            margin-top: 4px;
        }
    </style>
</head>

<body>

<?php include('dashboard/dist/navigation1.php'); ?>

<div class="main-wrap">
    <div class="content">
        <div class="login-style">
            <div class="profile-card">

                <h3>Manage Your Profile</h3>
                <p>Manage your personal information and profile picture.</p>

                <!-- Current avatar (from DB) -->
                <?php if (!empty($user['profilepicture'])): ?>
                    <div style="text-align:center; margin-bottom:1.5rem;">
                        <img src="/Presento/assets/profile/<?= htmlspecialchars($user['profilepicture']) ?>"
                             class="profile-preview" id="current-avatar">
                    </div>
                <?php endif; ?>

                <form action="manage-profile-process.php"
                      method="POST"
                      enctype="multipart/form-data"
                      id="profileForm">

                    <input type="hidden" name="current_user_id" value="<?= htmlspecialchars($user['user_id']) ?>">

                    <!-- Cropped image sent as base64; processor should detect + decode this -->
                    <input type="hidden" name="cropped_picture" id="cropped_picture">

                    <!-- Profile picture upload -->
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <!-- visible file input — triggers crop modal -->
                        <input type="file" name="picture" id="picture-input"
                               accept="image/*" class="form-control">
                        <!-- thumbnail preview after cropping -->
                        <div id="crop-thumb-wrap">
                            <img id="crop-thumb" src="" alt="Cropped preview">
                            <button type="button" id="crop-thumb-remove" title="Remove">✕</button>
                        </div>
                        <div id="crop-hint" style="display:none;">
                            Cropped image ready. Click <em>Update Profile</em> to save.
                        </div>
                    </div>

                    <!-- Student ID -->
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" name="user_id" class="form-control" required
                               value="<?= htmlspecialchars($user['user_id']) ?>">
                    </div>

                    <!-- First name -->
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="fname" class="form-control" required
                               value="<?= htmlspecialchars($user['fname']) ?>">
                    </div>

                    <!-- Last name -->
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="lname" class="form-control" required
                               value="<?= htmlspecialchars($user['lname']) ?>">
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label>New Password (Optional)</label>
                        <input type="password" name="password" class="form-control"
                               placeholder="Leave blank to keep current password">
                    </div>

                    <button type="submit" class="btn-login">Update Profile</button>

                </form>

            </div><!-- /profile-card -->
        </div><!-- /login-style -->
    </div><!-- /content -->

    <?php include('dashboard/dist/footer.php'); ?>
</div><!-- /main-wrap -->


<!-- ============================================================
     CROP MODAL
============================================================ -->
<div id="crop-modal" role="dialog" aria-modal="true" aria-labelledby="crop-modal-title">
    <div id="crop-modal-inner">
        <h5 id="crop-modal-title"><i class="bi bi-crop"></i> Crop Your Photo</h5>
        <div id="crop-image-wrap">
            <img id="crop-source" src="" alt="Image to crop">
        </div>
        <div id="crop-modal-actions">
            <button type="button" class="btn-crop-cancel" id="crop-cancel">Cancel</button>
            <button type="button" class="btn-crop-apply" id="crop-apply">Apply Crop</button>
        </div>
    </div>
</div>


<!-- Cropper.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

<script>
(function () {
    const fileInput    = document.getElementById('picture-input');
    const cropModal    = document.getElementById('crop-modal');
    const cropSource   = document.getElementById('crop-source');
    const cropApplyBtn = document.getElementById('crop-apply');
    const cropCancelBtn= document.getElementById('crop-cancel');
    const croppedField = document.getElementById('cropped_picture');
    const thumbWrap    = document.getElementById('crop-thumb-wrap');
    const thumb        = document.getElementById('crop-thumb');
    const thumbRemove  = document.getElementById('crop-thumb-remove');
    const cropHint     = document.getElementById('crop-hint');

    let cropper = null;

    /* --- Open modal when user picks a file --- */
    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Only accept images
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            fileInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            cropSource.src = e.target.result;
            openModal();
        };
        reader.readAsDataURL(file);
    });

    function openModal() {
        cropModal.classList.add('active');

        // Destroy previous instance if any
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }

        cropper = new Cropper(cropSource, {
            aspectRatio: 1,          // square crop → round avatar
            viewMode: 1,             // prevent crop box from exceeding the canvas
            movable: true,
            zoomable: true,
            scalable: false,
            autoCropArea: 0.8,
            preview: '',
        });
    }

    function closeModal() {
        cropModal.classList.remove('active');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    /* --- Apply crop --- */
    cropApplyBtn.addEventListener('click', function () {
        if (!cropper) return;

        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingQuality: 'high',
        });

        const dataUrl = canvas.toDataURL('image/jpeg', 0.88);

        // Store in hidden field for the processor
        croppedField.value = dataUrl;

        // Show thumbnail preview
        thumb.src = dataUrl;
        thumbWrap.style.display = 'block';
        cropHint.style.display  = 'block';

        // Clear the file input so the processor uses cropped_picture instead
        fileInput.value = '';

        closeModal();
    });

    /* --- Cancel --- */
    cropCancelBtn.addEventListener('click', function () {
        fileInput.value = '';
        closeModal();
    });

    /* --- Remove chosen crop --- */
    thumbRemove.addEventListener('click', function () {
        croppedField.value      = '';
        thumb.src               = '';
        thumbWrap.style.display = 'none';
        cropHint.style.display  = 'none';
        fileInput.value         = '';
    });

    /* --- Close modal on backdrop click --- */
    cropModal.addEventListener('click', function (e) {
        if (e.target === cropModal) {
            fileInput.value = '';
            closeModal();
        }
    });
})();
</script>

</body>
</html>