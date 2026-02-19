<?php
// This endpoint is not used by the prayers module.
// Prayers uses photo_url on prayers_posts directly, not a separate attachments table.
// Photo removal is handled by update.php with remove_photo=true.
header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode(['success' => false, 'error' => 'not_applicable']);
