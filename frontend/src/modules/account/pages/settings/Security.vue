<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { History, Laptop, ShieldAlert } from '@lucide/vue';
import { ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import type { Props as ManagePasskeysProps } from '@modules/account/components/ManagePasskeys.vue';
import ManagePasskeys from '@modules/account/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@modules/account/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@modules/account/components/ManageTwoFactor.vue';
import type {
    BrowserSession,
    SecurityAuditEvent,
} from '@modules/account/types';
import Heading from '@shared/components/Heading.vue';
import InputError from '@shared/components/InputError.vue';
import PasswordInput from '@shared/components/PasswordInput.vue';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import { Label } from '@shared/components/ui/label';
import { edit } from '@/routes/security';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    sessions?: BrowserSession[];
    recentSecurityEvents?: SecurityAuditEvent[];
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = withDefaults(defineProps<Props>(), {
    sessions: () => [],
    recentSecurityEvents: () => [],
});

const revokeDialogOpen = ref(false);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Security settings" />

    <h1 class="sr-only">Security settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Update password"
            description="Ensure your account is using a long, random password to stay secure"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="New password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Save
                </Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />

    <!-- Active Browser Sessions -->
    <div class="space-y-4">
        <div
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <Heading
                variant="small"
                title="Browser sessions"
                description="Review active devices and sign out of other browser sessions"
            />

            <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-10 self-start sm:self-auto"
                data-test="revoke-other-sessions-button"
                @click="revokeDialogOpen = true"
            >
                Log out other browser sessions
            </Button>
        </div>

        <ul v-if="sessions.length > 0" class="space-y-2.5">
            <li
                v-for="session in sessions"
                :key="session.id"
                class="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm"
            >
                <div class="flex items-center gap-3">
                    <Laptop
                        class="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="font-medium text-foreground">
                            {{ session.device }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ session.ipAddress ?? 'Unknown IP' }} ·
                            {{ session.lastActiveDiff }}
                        </p>
                    </div>
                </div>

                <Badge
                    v-if="session.isCurrent"
                    variant="secondary"
                    class="bg-brand-subtle text-xs font-semibold text-brand-foreground"
                >
                    This device
                </Badge>
            </li>
        </ul>

        <Dialog v-model:open="revokeDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Log out other browser sessions</DialogTitle>
                    <DialogDescription>
                        Confirm your password to sign out of your account on all
                        other browsers and devices.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    v-bind="SecurityController.destroyOtherSessions.form()"
                    reset-on-success
                    :reset-on-error="['current_password']"
                    :options="{ preserveScroll: true }"
                    class="space-y-4"
                    @success="revokeDialogOpen = false"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="revoke_current_password">
                            Current password
                        </Label>
                        <PasswordInput
                            id="revoke_current_password"
                            name="current_password"
                            autocomplete="current-password"
                            placeholder="Current password"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="revokeDialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="processing"
                            data-test="confirm-revoke-sessions-button"
                        >
                            Log out other sessions
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>

    <!-- Recent Security Activity Audit Log -->
    <div class="space-y-4">
        <Heading
            variant="small"
            title="Recent security activity"
            description="Audit log of recent sign-ins, password changes, and account security events"
        />

        <ul v-if="recentSecurityEvents.length > 0" class="space-y-2">
            <li
                v-for="item in recentSecurityEvents"
                :key="item.id"
                class="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm"
            >
                <div class="flex items-center gap-3">
                    <History
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="font-medium text-foreground">
                            {{ item.label }}
                        </p>
                        <p
                            v-if="item.ipAddress"
                            class="text-xs text-muted-foreground"
                        >
                            IP: {{ item.ipAddress }}
                        </p>
                    </div>
                </div>
                <span class="shrink-0 text-xs text-muted-foreground">
                    {{ item.occurredAtDiff }}
                </span>
            </li>
        </ul>

        <div
            v-else
            class="flex items-center gap-2.5 rounded-xl border border-dashed border-border px-4 py-4 text-xs text-muted-foreground"
        >
            <ShieldAlert class="size-4 shrink-0" aria-hidden="true" />
            <span>No security events recorded for this account yet.</span>
        </div>
    </div>
</template>
