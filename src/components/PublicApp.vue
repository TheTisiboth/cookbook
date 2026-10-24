<!--
SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors

SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later
-->

<template>
    <div class="cookbook-public">
        <RecipeView :public-token="token" />
        <NcDialog
            :open="isDialogOpen"
            :buttons="buttons"
            :name="dialogTitle"
            :message="dialogMessage"
            :no-close="!(dialogAllowClose ?? true)"
            @update:open="close"
        />
    </div>
</template>

<script setup lang="ts">
import NcDialog from '@nextcloud/vue/components/NcDialog';
import { useCookbookDialogs } from 'cookbook/composables/useCookbookDialogs';
import RecipeView from './RecipeView/RecipeView.vue';

defineProps<{ token: string }>();

const {
    close,
    isDialogOpen,
    buttons,
    dialogTitle,
    dialogMessage,
    dialogAllowClose,
} = useCookbookDialogs();
</script>

<script lang="ts">
export default {
    name: 'PublicApp',
};
</script>

<style scoped>
.cookbook-public {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}
</style>
