<?php
/**
 * Gospel Media Permissions Library
 * Room-based access and posting rights
 */

// No auth_gate.php include here - this is just a library of functions

/**
 * Check if user has access to view a room
 */
function user_has_access_to_room(PDO $pdo, int $userId, array $room): bool {
    if (!$room || !isset($room['id'])) return false;
    
    // Get user info with birthdate for age calculation
    $st = $pdo->prepare("SELECT amp_id, town_id, congregation_id, birthdate, marital_status FROM users WHERE id = ?");
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) return false;
    
    $ampId = (int)($user['amp_id'] ?? 999);
    $townId = (int)($user['town_id'] ?? 0);
    $congId = (int)($user['congregation_id'] ?? 0);
    $birthdate = $user['birthdate'] ?? null;
    $maritalStatus = $user['marital_status'] ?? '';
    
    // Calculate age
    $age = 0;
    if ($birthdate) {
        $dob = new DateTime($birthdate);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
    }
    
    $roomType = strtolower($room['type'] ?? '');
    $roomTownId = (int)($room['town_id'] ?? 0);
    $roomGemeenteId = (int)($room['gemeente_id'] ?? 0);

    // Admin (amp 0): full access to all rooms in own town
    if ($ampId === 0) {
        if ($roomType === 'gemeenskap') return true;
        if ($roomTownId === $townId) return true;
        if ($roomType === 'gemeente' && $roomGemeenteId > 0) {
            $ct = $pdo->prepare("SELECT town_id FROM congregations WHERE id = ?");
            $ct->execute([$roomGemeenteId]);
            if ((int)$ct->fetchColumn() === $townId) return true;
        }
        return false;
    }

    // Gemeenskap - everyone
    if ($roomType === 'gemeenskap') return true;

    // Apostel - only opsienerskappe
    if ($ampId === 1) {
        if ($roomType === 'opsienerskap') {
            // Check membership
            $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
            $ms->execute([$room['id'], $userId]);
            return (bool)$ms->fetchColumn();
        }
        return false;
    }
    
    // Own gemeente - always access (for amp 1-7)
    if ($roomType === 'gemeente') {
        if ($roomGemeenteId === $congId) return true;
        
        // Amp 8, 9, 10 can ONLY see their gemeente unless explicitly added
        if ($ampId >= 8) {
            $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
            $ms->execute([$room['id'], $userId]);
            return (bool)$ms->fetchColumn();
        }
        
        // Check membership for others
        $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
        $ms->execute([$room['id'], $userId]);
        return (bool)$ms->fetchColumn();
    }
    
    // Own town opsienerskap
    if ($roomType === 'opsienerskap') {
        if ($roomTownId === $townId) return true;
    }
    
    // Sondagskool - auto-access if under 16 and in same town, or amp 2-5 in same town
    if ($roomType === 'sondagskool') {
        if ($ampId >= 2 && $ampId <= 5 && $roomTownId === $townId) return true;
        if ($age > 0 && $age < 16 && $roomTownId === $townId) return true;
        
        // Amp 8, 9, 10 need explicit membership
        if ($ampId >= 8) {
            $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
            $ms->execute([$room['id'], $userId]);
            return (bool)$ms->fetchColumn();
        }
        
        // Check membership for others
        $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
        $ms->execute([$room['id'], $userId]);
        return (bool)$ms->fetchColumn();
    }
    
    // Jeug - auto-access if 16-25 unmarried and in same town, or amp 2-5 in same town
    if ($roomType === 'jeug') {
        if ($ampId >= 2 && $ampId <= 5 && $roomTownId === $townId) return true;
        if ($age >= 16 && $age <= 25 && $maritalStatus === 'ongetroud' && $roomTownId === $townId) return true;
        
        // Amp 8, 9, 10 need explicit membership
        if ($ampId >= 8) {
            $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
            $ms->execute([$room['id'], $userId]);
            return (bool)$ms->fetchColumn();
        }
        
        // Check membership for others
        $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
        $ms->execute([$room['id'], $userId]);
        return (bool)$ms->fetchColumn();
    }
    
    // Other rooms - check membership
    $ms = $pdo->prepare("SELECT 1 FROM room_memberships WHERE room_id = ? AND user_id = ? LIMIT 1");
    $ms->execute([$room['id'], $userId]);
    return (bool)$ms->fetchColumn();
}

/**
 * Check if user can post in a room
 */
function user_can_post_in_room(PDO $pdo, int $userId, array $room): bool {
    if (!user_has_access_to_room($pdo, $userId, $room)) return false;
    
    // Get user info
    $st = $pdo->prepare("SELECT amp_id FROM users WHERE id = ?");
    $st->execute([$userId]);
    $ampId = (int)($st->fetchColumn() ?: 999);
    
    $roomType = strtolower($room['type'] ?? '');
    
    // Admin can post anywhere they have access
    if ($ampId === 0) return true;

    // Posting rights by room type
    switch ($roomType) {
        case 'opsienerskap':
            // Amp 2-5: Opsiener, Evangelis, Profeet, Apostel
            return ($ampId >= 1 && $ampId <= 5);
            
        case 'gemeente':
            // Amp 5-9: Oudste, Priester, Onderdiaken, Koorleier, Hulpkrag
            return ($ampId >= 5 && $ampId <= 9);
            
        case 'jeug':
        case 'sondagskool':
            // Amp 1-9: Everyone except Broer/Suster (amp 10)
            return ($ampId >= 1 && $ampId <= 9);
            
        case 'gemeenskap':
            // Everyone can post
            return true;
            
        default:
            return false;
    }
}

/**
 * Check if user can edit/delete posts in a room
 */
function user_can_edit_post(PDO $pdo, int $userId, array $post, ?array $room = null): bool {
    // Owner can always edit their own post
    if ((int)($post['user_id'] ?? 0) === $userId) return true;
    
    // Get room if not provided
    if (!$room) {
        $rs = $pdo->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
        $rs->execute([(int)($post['room_id'] ?? 0)]);
        $room = $rs->fetch(PDO::FETCH_ASSOC);
        if (!$room) return false;
    }
    
    // Get user's amp_id
    $st = $pdo->prepare("SELECT amp_id FROM users WHERE id = ?");
    $st->execute([$userId]);
    $ampId = (int)($st->fetchColumn() ?: 999);
    
    $roomType = strtolower($room['type'] ?? '');

    // Admin can edit/delete any post
    if ($ampId === 0) return true;

    // Room-based edit rights (officers can moderate)
    switch ($roomType) {
        case 'opsienerskap':
            // Amp 2-5 can edit/delete any post in opsienerskap
            return ($ampId >= 2 && $ampId <= 5);
            
        case 'gemeente':
            // Amp 5-9 can edit/delete any post in gemeente
            return ($ampId >= 5 && $ampId <= 9);
            
        case 'jeug':
        case 'sondagskool':
            // Amp 1-9 can edit/delete any post in jeug/sondagskool
            return ($ampId >= 1 && $ampId <= 9);
            
        case 'gemeenskap':
            // Amp 1-6 can moderate gemeenskap
            return ($ampId >= 1 && $ampId <= 6);
            
        default:
            return false;
    }
}

/**
 * Check if user can edit/delete comments in a room
 */
function user_can_edit_comment(PDO $pdo, int $userId, array $comment, int $postId): bool {
    // Owner can always edit their own comment
    if ((int)($comment['user_id'] ?? 0) === $userId) return true;
    
    // Get post and room info
    $ps = $pdo->prepare("SELECT room_id FROM posts WHERE id = ? LIMIT 1");
    $ps->execute([$postId]);
    $roomId = (int)($ps->fetchColumn() ?: 0);
    
    if ($roomId <= 0) return false;
    
    $rs = $pdo->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    $rs->execute([$roomId]);
    $room = $rs->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) return false;
    
    // Get user's amp_id
    $st = $pdo->prepare("SELECT amp_id FROM users WHERE id = ?");
    $st->execute([$userId]);
    $ampId = (int)($st->fetchColumn() ?: 999);
    
    $roomType = strtolower($room['type'] ?? '');

    // Admin can edit/delete any comment
    if ($ampId === 0) return true;

    // Same rules as post editing
    switch ($roomType) {
        case 'opsienerskap':
            return ($ampId >= 2 && $ampId <= 5);
            
        case 'gemeente':
            return ($ampId >= 5 && $ampId <= 9);
            
        case 'jeug':
        case 'sondagskool':
            return ($ampId >= 1 && $ampId <= 9);
            
        case 'gemeenskap':
            return ($ampId >= 1 && $ampId <= 6);
            
        default:
            return false;
    }
}