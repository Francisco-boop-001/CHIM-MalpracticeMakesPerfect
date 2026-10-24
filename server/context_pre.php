<?php
declare(strict_types=1);

function brainwash_context_pre_beliefs(array $context): string
{
    $npc = $GLOBALS['CHIM_CORE_CURRENT_NPC_DATA'] ?? null;
    if (!is_array($npc)) return '';
    $id = filter_var($npc['id'] ?? null, FILTER_VALIDATE_INT);
    if (!is_int($id) || $id < 1) return '';

    $name = $npc['npc_name'] ?? null;
    $promptName = $context['herika_name'] ?? null;
    $globalName = $GLOBALS['HERIKA_NAME'] ?? null;
    if (!is_string($name) || $name === '' || !is_string($promptName) || $promptName !== $name
        || !is_string($globalName) || $globalName !== $name) return '';

    $profile = $GLOBALS['CHIM_CORE_CURRENT_PROFILE_DATA'] ?? null;
    $rowProfileId = $npc['profile_id'] ?? null;
    $currentProfileId = is_array($profile) ? ($profile['id'] ?? $profile['profile_id'] ?? null) : null;
    if ($rowProfileId !== null && $currentProfileId !== null
        && (int)$rowProfileId !== (int)$currentProfileId) return '';

    $extended = $npc['plugin_extended_data'] ?? null;
    if (is_string($extended)) {
        try {
            $extended = json_decode($extended, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }
    }
    if (!is_array($extended) || !is_array($extended['brainwash'] ?? null)) return '';
    $beliefs = $extended['brainwash']['beliefs'] ?? null;
    if (!is_string($beliefs) || strlen($beliefs) > 4000 || preg_match('//u', $beliefs) !== 1 || trim($beliefs) === '') return '';
    return "User-authored fictional beliefs (not verified facts):\n" . $beliefs;
}

if (function_exists('chimRegisterPromptInjection')) {
    \chimRegisterPromptInjection('character_bottom', 'brainwash', static function (string $_slot, array $context): string {
        try {
            return brainwash_context_pre_beliefs($context);
        } catch (Throwable) {
            return '';
        }
    }, 80);
}
