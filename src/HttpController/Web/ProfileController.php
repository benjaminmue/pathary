<?php declare(strict_types=1);

namespace Movary\HttpController\Web;

use Movary\Domain\User\Service\Authentication;
use Movary\Domain\User\UserApi;
use Movary\ValueObject\Http\Header;
use Movary\ValueObject\Http\Request;
use Movary\ValueObject\Http\Response;
use Movary\ValueObject\Http\StatusCode;
use RuntimeException;
use Twig\Environment;

class ProfileController
{
    private const string PROFILE_IMAGES_DIR = __DIR__ . '/../../../storage/profile-images';
    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private readonly Environment $twig,
        private readonly Authentication $authenticationService,
        private readonly UserApi $userApi,
    ) {
    }

    public function show(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $user = $this->userApi->fetchUser($userId);
        $email = $this->userApi->findUserEmail($userId);
        $profileImage = $this->userApi->findProfileImage($userId);

        return Response::create(
            StatusCode::createOk(),
            $this->twig->render('public/profile.twig', [
                'userName' => $user->getName(),
                'userEmail' => $email,
                'profileImage' => $profileImage,
                'success' => $request->getGetParameters()['success'] ?? null,
                'error' => $request->getGetParameters()['error'] ?? null,
            ]),
        );
    }

    public function update(Request $request) : Response
    {
        $userId = $this->authenticationService->getCurrentUserId();
        $user = $this->userApi->fetchUser($userId);
        $postData = $request->getPostParameters();
        $files = $_FILES;

        // Check if core account changes are disabled
        if ($user->hasCoreAccountChangesDisabled() === true) {
            return Response::createSeeOther('/profile?error=' . urlencode('Account changes are disabled for this user.'));
        }

        try {
            // Update name if provided
            $name = trim($postData['name'] ?? '');
            if ($name !== '' && $name !== $user->getName()) {
                $this->userApi->updateName($userId, $name);
            }

            // Update email if provided
            $email = trim($postData['email'] ?? '');
            $currentEmail = $this->userApi->findUserEmail($userId);
            if ($email !== '' && $email !== $currentEmail) {
                $this->userApi->updateEmail($userId, $email);
            }

            // Handle profile image upload
            if (isset($files['profile_image']) && $files['profile_image']['error'] === UPLOAD_ERR_OK) {
                $this->handleProfileImageUpload($userId, $files['profile_image']);
            }

            // Handle profile image removal
            if (isset($postData['remove_image']) && $postData['remove_image'] === '1') {
                $this->removeProfileImage($userId);
            }

            return Response::createSeeOther('/profile?success=' . urlencode('Profile updated successfully.'));
        } catch (RuntimeException $e) {
            return Response::createSeeOther('/profile?error=' . urlencode($e->getMessage()));
        }
    }

    public function serveImage(Request $request) : Response
    {
        $filename = $request->getRouteParameters()['filename'];

        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filepath = self::PROFILE_IMAGES_DIR . '/' . $filename;

        if (file_exists($filepath) === false) {
            return Response::createNotFound();
        }

        $mimeType = mime_content_type($filepath);
        $content = file_get_contents($filepath);

        return Response::create(
            StatusCode::createOk(),
            $content,
            [
                Header::createContentType($mimeType),
                Header::createCache(31536000),
                Header::createNoSniff(),
            ],
        );
    }

    private function handleProfileImageUpload(int $userId, array $file) : void
    {
        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Image file is too large. Maximum size is 5MB.');
        }

        // Validate MIME type using file contents (not just extension)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (in_array($mimeType, self::ALLOWED_MIME_TYPES, true) === false) {
            throw new RuntimeException('Invalid image type. Allowed types: JPEG, PNG, GIF, WebP.');
        }

        // Additional validation: verify it's actually an image by getting image info
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new RuntimeException('File does not appear to be a valid image.');
        }

        // Verify the detected image type matches the MIME type
        $expectedMimeFromImage = image_type_to_mime_type($imageInfo[2]);
        if ($expectedMimeFromImage !== $mimeType) {
            throw new RuntimeException('Image content does not match file type.');
        }

        // Create storage directory if it doesn't exist
        if (is_dir(self::PROFILE_IMAGES_DIR) === false) {
            mkdir(self::PROFILE_IMAGES_DIR, 0755, true);
        }

        // Generate random filename (never use user-provided filename)
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $filepath = self::PROFILE_IMAGES_DIR . '/' . $filename;

        // Remove old profile image if exists
        $this->removeProfileImage($userId);

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath) === false) {
            throw new RuntimeException('Failed to save profile image.');
        }

        // Update database
        $this->userApi->updateProfileImage($userId, $filename);
    }

    private function removeProfileImage(int $userId) : void
    {
        $currentImage = $this->userApi->findProfileImage($userId);

        if ($currentImage !== null) {
            $filepath = self::PROFILE_IMAGES_DIR . '/' . $currentImage;
            if (file_exists($filepath) === true) {
                unlink($filepath);
            }
            $this->userApi->updateProfileImage($userId, null);
        }
    }
}
