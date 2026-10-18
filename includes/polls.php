<?php
/** Private club opinion polls. Never return ballot identities or premature totals. */
function club_poll_now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('Europe/London')); }

function club_poll_state(array $poll, ?DateTimeImmutable $now = null): string
{
    if ($poll['status'] !== 'published') return $poll['status'];
    $now ??= club_poll_now();
    $zone = new DateTimeZone('Europe/London');
    if ($now < new DateTimeImmutable($poll['opens_at'], $zone)) return 'scheduled';
    return $now >= new DateTimeImmutable($poll['closes_at'], $zone) ? 'closed' : 'open';
}

function club_poll_transaction(callable $work): mixed
{
    $pdo = db();
    $nested = $pdo->inTransaction();
    if ($nested) $pdo->exec('SAVEPOINT poll_work'); else $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        if ($nested) $pdo->exec('RELEASE SAVEPOINT poll_work'); else $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($nested) $pdo->exec('ROLLBACK TO SAVEPOINT poll_work'); elseif ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function club_poll_actor(): array
{
    $user = club_current_user();
    if (!$user) throw new DomainException('Please log in to use club polls.');
    return $user;
}

function club_poll_find(int $id, bool $lock = false): array
{
    $q = db()->prepare('SELECT * FROM club_polls WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $q->execute([$id]);
    return $q->fetch() ?: throw new DomainException('Poll not found.');
}

function club_poll_choices(int $id): array
{
    $q = db()->prepare('SELECT id, label FROM club_poll_choices WHERE poll_id = ? ORDER BY sort_order, id');
    $q->execute([$id]);
    return $q->fetchAll();
}

function club_poll_can_view(array $poll): bool
{
    $user = club_current_user();
    return $user && (club_can('content.publish') || ($poll['status'] !== 'published' && (int)$poll['author_user_id'] === (int)$user['id']) ||
        (club_is_active_member() && $poll['status'] === 'published'));
}

/** Optional user ID may only identify the current actor, never impersonate another member. */
function club_poll_list(?int $userId = null): array
{
    $user = club_current_user();
    if (!$user || ($userId !== null && $userId !== (int)$user['id'])) return [];
    if (club_can('content.publish')) return db()->query('SELECT * FROM club_polls ORDER BY created_at DESC')->fetchAll();
    $q = db()->prepare("SELECT * FROM club_polls WHERE (author_user_id = ? AND status <> 'published')" .
        (club_is_active_member() ? " OR status = 'published'" : '') . ' ORDER BY closes_at DESC, id DESC');
    $q->execute([$user['id']]);
    return $q->fetchAll();
}

/** Dates are local club times; reject invalid and ambiguous daylight-saving clock times. */
function club_poll_date(string $input): string
{
    $text = str_replace('T', ' ', trim($input));
    if (strlen($text) === 16) $text .= ':00';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $text, new DateTimeZone('Europe/London'));
    if (!$date || $date->format('Y-m-d H:i:s') !== $text) throw new DomainException('Choose a valid date and time (UK time).');
    // Repeated autumn hours cannot be represented unambiguously by the local DATETIME schema.
    $transitions = (new DateTimeZone('Europe/London'))->getTransitions($date->getTimestamp() - 7200, $date->getTimestamp() + 7200);
    for ($i = 1; $i < count($transitions); $i++) {
        if ($transitions[$i]['offset'] < $transitions[$i-1]['offset']) {
            $start = gmdate('Y-m-d H:i:s', $transitions[$i]['ts'] + $transitions[$i]['offset']);
            $end = gmdate('Y-m-d H:i:s', $transitions[$i]['ts'] + $transitions[$i-1]['offset']);
            if ($text >= $start && $text < $end) throw new DomainException('That UK time occurs twice when the clocks change. Please choose another time.');
        }
    }
    return $text;
}

function club_poll_save(?int $id, array $input): int
{
    $user = club_poll_actor();
    if (!club_can('content.submit')) throw new DomainException('An active approved membership is required to submit polls.');
    $title = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $rawChoices = $input['choices'] ?? [];
    if (!is_array($rawChoices) || array_filter($rawChoices, fn($s) => !is_string($s))) throw new DomainException('Enter text for each answer choice.');
    $choices = array_values(array_filter(array_map('trim', $rawChoices), fn($s) => $s !== ''));
    if ($title === '' || mb_strlen($title) > 255 || mb_strlen($description) > 10000) throw new DomainException('Enter a question up to 255 characters and a shorter explanation.');
    if (count($choices) < 2 || count($choices) > 10 || count(array_unique($choices)) !== count($choices)) throw new DomainException('Provide between two and ten different choices.');
    foreach ($choices as $label) if (mb_strlen($label) > 255) throw new DomainException('Each choice must be no longer than 255 characters.');
    $opens = club_poll_date((string)($input['opens_at'] ?? ''));
    $closes = club_poll_date((string)($input['closes_at'] ?? ''));
    if ($closes <= $opens || $closes <= club_poll_now()->format('Y-m-d H:i:s')) throw new DomainException('Closing must be after opening and in the future.');
    $status = ($input['action'] ?? '') === 'submit' ? 'submitted' : 'draft';
    return club_poll_transaction(function(PDO $pdo) use($id, $user, $title, $description, $choices, $opens, $closes, $status) {
        if ($id) {
            $poll = club_poll_find($id, true);
            if ((int)$poll['author_user_id'] !== (int)$user['id']) throw new DomainException('You can only edit your own polls.');
            if (!in_array($poll['status'], ['draft','rejected'], true)) throw new DomainException('This poll is locked. Cancel a published poll and create a replacement if changes are needed.');
            $v = $pdo->prepare('SELECT 1 FROM club_poll_votes WHERE poll_id = ? LIMIT 1'); $v->execute([$id]);
            if ($v->fetchColumn()) throw new DomainException('A poll with accepted votes cannot be changed.');
            $pdo->prepare('UPDATE club_polls SET title=?, description=?, opens_at=?, closes_at=?, status=?, reviewer_note=NULL WHERE id=?')->execute([$title,$description,$opens,$closes,$status,$id]);
            $pdo->prepare('DELETE FROM club_poll_choices WHERE poll_id=?')->execute([$id]);
        } else {
            $pdo->prepare('INSERT INTO club_polls (author_user_id,title,description,opens_at,closes_at,status) VALUES (?,?,?,?,?,?)')->execute([$user['id'],$title,$description,$opens,$closes,$status]);
            $id = (int)$pdo->lastInsertId();
        }
        $stmt = $pdo->prepare('INSERT INTO club_poll_choices (poll_id,label,sort_order) VALUES (?,?,?)');
        foreach ($choices as $order => $label) $stmt->execute([$id,$label,$order]);
        return $id;
    });
}

function club_poll_vote(int $id, int $choice): void
{
    $user = club_poll_actor();
    if (!club_can('polls.vote') || !club_is_active_member()) throw new DomainException('Only current, approved members can vote.');
    club_poll_transaction(function(PDO $pdo) use($id, $choice, $user) {
        $poll = club_poll_find($id, true); // Serialises votes with moderation and closing.
        if (club_poll_state($poll) !== 'open') throw new DomainException('This poll is not open for voting.');
        // Recheck eligibility from locked current records instead of accepting client IDs or cached status.
        $q = $pdo->prepare("SELECT id FROM club_users WHERE id=? AND status='active' AND email_verified_at IS NOT NULL FOR UPDATE");
        $q->execute([$user['id']]);
        if (!$q->fetch()) throw new DomainException('Your account is not eligible to vote.');
        $q = $pdo->prepare("SELECT id FROM club_memberships WHERE user_id=? AND status='active' AND starts_on<=? AND expires_on>? FOR UPDATE");
        $today = club_poll_now()->format('Y-m-d'); $q->execute([$user['id'],$today,$today]);
        if (!$q->fetch()) throw new DomainException('Your membership is not currently active.');
        $q = $pdo->prepare('SELECT id FROM club_poll_choices WHERE poll_id=? AND id=?'); $q->execute([$id,$choice]);
        if (!$q->fetch()) throw new DomainException('Choose one of this poll’s options.');
        $pdo->prepare('INSERT INTO club_poll_votes (poll_id,user_id,choice_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE choice_id=VALUES(choice_id), updated_at=CURRENT_TIMESTAMP')->execute([$id,$user['id'],$choice]);
    });
}

function club_poll_my_vote(int $id): ?int
{
    $user = club_poll_actor();
    $poll = club_poll_find($id);
    if (!club_poll_can_view($poll)) throw new DomainException('This poll is unavailable.');
    $q = db()->prepare('SELECT choice_id FROM club_poll_votes WHERE poll_id=? AND user_id=?'); $q->execute([$id,$user['id']]);
    $choice = $q->fetchColumn(); return $choice === false ? null : (int)$choice;
}

function club_poll_results(int $id): array
{
    $poll = club_poll_find($id);
    if (!club_poll_can_view($poll)) throw new DomainException('This poll is unavailable.');
    if (club_poll_state($poll) !== 'closed') throw new DomainException('Results are available only after voting closes.');
    $q = db()->prepare('SELECT c.id,c.label,COUNT(v.user_id) AS votes FROM club_poll_choices c LEFT JOIN club_poll_votes v ON v.poll_id=c.poll_id AND v.choice_id=c.id WHERE c.poll_id=? GROUP BY c.id,c.label,c.sort_order ORDER BY c.sort_order,c.id');
    $q->execute([$id]); $rows = $q->fetchAll();
    $total = array_sum(array_column($rows,'votes'));
    foreach ($rows as &$row) { $row['votes'] = (int)$row['votes']; $row['percentage'] = $total ? round($row['votes'] / $total * 100, 1) : 0; }
    return ['total' => $total, 'choices' => $rows];
}

function club_poll_moderate(int $id, string $action, string $reason = ''): void
{
    $user = club_poll_actor();
    if (!club_can('content.publish')) throw new DomainException('Publishing permission is required.');
    if (!in_array($action,['publish','reject','cancel','close'],true)) throw new DomainException('Unknown poll action.');
    $reason = trim($reason);
    if ($action !== 'publish' && ($reason === '' || mb_strlen($reason) > 2000)) throw new DomainException('Please give a reason (up to 2000 characters).');
    club_poll_transaction(function(PDO $pdo) use($id,$action,$reason,$user) {
        $poll = club_poll_find($id,true);
        $state = club_poll_state($poll);
        if (in_array($action,['publish','reject'],true) && $poll['status'] !== 'submitted') throw new DomainException('Only submitted polls can be reviewed.');
        if ($action === 'publish' && ($poll['closes_at'] <= club_poll_now()->format('Y-m-d H:i:s') || count(club_poll_choices($id)) < 2)) throw new DomainException('This poll needs future closing and at least two choices.');
        if ($action === 'close' && $state !== 'open') throw new DomainException('Only open polls can be closed early.');
        if ($action === 'cancel' && in_array($state,['closed','cancelled'],true)) throw new DomainException('This poll has already ended.');
        if ($action === 'close') $pdo->prepare('UPDATE club_polls SET closes_at=?, reviewer_note=? WHERE id=?')->execute([club_poll_now()->format('Y-m-d H:i:s'),$reason,$id]);
        else $pdo->prepare('UPDATE club_polls SET status=?, reviewer_note=?, approved_by=? WHERE id=?')->execute([['publish'=>'published','reject'=>'rejected','cancel'=>'cancelled'][$action],$reason,$user['id'],$id]);
        // Only lifecycle data enters audit; no ballot contents or voter identities.
        $pdo->prepare("INSERT INTO club_outbox(channel,event_key,payload,status) VALUES('audit',?,?,'sent')")->execute([
            'poll-'.bin2hex(random_bytes(16)), json_encode(['action'=>'poll.'.$action,'poll_id'=>$id,'actor_user_id'=>$user['id'],'reason'=>$reason],JSON_THROW_ON_ERROR)
        ]);
    });
}
