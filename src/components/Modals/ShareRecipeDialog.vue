<!--
SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors

SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later
-->

<template>
    <NcDialog
        :name="t('cookbook', 'Share recipe')"
        size="normal"
        :open="true"
        @update:open="emit('close')"
    >
        <div class="share-dialog">
            <p>
                {{
                    /* prettier-ignore */
                    t('cookbook', 'Anyone with the public link can view this recipe, without needing an account. They cannot edit it.')
                }}
            </p>

            <NcLoadingIcon v-if="isLoading" :size="32" />

            <NcNoteCard v-else-if="errorMessage" type="error">
                {{ errorMessage }}
            </NcNoteCard>

            <div v-else-if="share" class="share-link">
                <NcTextField
                    :model-value="share.url"
                    :label="t('cookbook', 'Public link')"
                    readonly
                    @focus="selectAll"
                />
                <div class="share-buttons">
                    <NcButton variant="primary" @click="copyLink">
                        <template #icon>
                            <ContentCopyIcon :size="20" />
                        </template>
                        {{ t('cookbook', 'Copy link') }}
                    </NcButton>
                    <NcButton
                        variant="error"
                        :disabled="isUpdating"
                        @click="deleteLink"
                    >
                        <template #icon>
                            <LinkOffIcon :size="20" />
                        </template>
                        {{ t('cookbook', 'Remove public link') }}
                    </NcButton>
                </div>
            </div>

            <NcButton
                v-else
                variant="primary"
                :disabled="isUpdating"
                @click="createLink"
            >
                <template #icon>
                    <LinkIcon :size="20" />
                </template>
                {{ t('cookbook', 'Create public link') }}
            </NcButton>
        </div>
    </NcDialog>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import NcButton from '@nextcloud/vue/components/NcButton';
import NcDialog from '@nextcloud/vue/components/NcDialog';
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon';
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard';
import NcTextField from '@nextcloud/vue/components/NcTextField';
import { showError, showSuccess } from '@nextcloud/dialogs';
import ContentCopyIcon from 'icons/ContentCopy.vue';
import LinkIcon from 'icons/Link.vue';
import LinkOffIcon from 'icons/LinkOff.vue';

import api from 'cookbook/js/api-interface';
import type { PublicShare } from '../../types/PublicShare';
import type { RequestError } from '../../types/RequestError';

const props = defineProps<{ recipeId: number | string }>();
const emit = defineEmits<{ close: [] }>();

const t = window.t;

const share = ref<PublicShare | null>(null);
const isLoading = ref(true);
const isUpdating = ref(false);
const errorMessage = ref('');

const extractErrorMessage = (e: unknown, fallback: string): string =>
    (e as RequestError).response?.data?.msg || fallback;

const loadShare = async () => {
    try {
        share.value = (await api.recipes.publicShare.get(props.recipeId)).data;
    } catch (e) {
        errorMessage.value = extractErrorMessage(
            e,
            t('cookbook', 'Loading the public link failed'),
        );
    } finally {
        isLoading.value = false;
    }
};

const updateLink = async (
    action: () => Promise<PublicShare | null>,
    errorFallback: string,
) => {
    isUpdating.value = true;
    try {
        share.value = await action();
    } catch (e) {
        showError(extractErrorMessage(e, errorFallback));
    } finally {
        isUpdating.value = false;
    }
};

const createLink = () =>
    updateLink(
        async () => (await api.recipes.publicShare.create(props.recipeId)).data,
        t('cookbook', 'Creating the public link failed'),
    );

const deleteLink = () =>
    updateLink(
        async () => {
            await api.recipes.publicShare.delete(props.recipeId);
            return null;
        },
        t('cookbook', 'Removing the public link failed'),
    );

const copyLink = async () => {
    const item = t('cookbook', 'Public link');
    try {
        await navigator.clipboard.writeText(share.value?.url ?? '');
        showSuccess(t('cookbook', '{item} copied to clipboard', { item }));
    } catch {
        showError(
            t('cookbook', 'Copying {item} to clipboard failed', { item }),
        );
    }
};

const selectAll = (event: FocusEvent) => {
    (event.target as HTMLInputElement).select();
};

onMounted(loadShare);
</script>

<style scoped>
.share-dialog {
    display: flex;
    flex-direction: column;
    padding-bottom: 1em;
    gap: 1em;
}

.share-link {
    display: flex;
    flex-direction: column;
    gap: 0.5em;
}

.share-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5em;
}
</style>
