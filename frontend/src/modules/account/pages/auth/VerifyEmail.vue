<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@shared/components/InputError.vue';
import TextLink from '@shared/components/TextLink.vue';
import { Button } from '@shared/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@shared/components/ui/input-otp';
import { Spinner } from '@shared/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { verify as verifyCode } from '@/routes/verification/code';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Enter the 6-digit verification code we emailed to you, or click the verification link in your inbox.',
    },
});

defineProps<{
    status?: string;
}>();

const page = usePage();
const userEmail = computed(() => page.props.auth?.user?.email ?? '');
const code = ref<string>('');
</script>

<template>
    <Head title="Email verification" />

    <div class="space-y-6">
        <p v-if="userEmail" class="text-center text-xs text-muted-foreground">
            Verification code sent to
            <strong class="font-semibold text-foreground">{{
                userEmail
            }}</strong>
        </p>

        <div
            v-if="status === 'verification-link-sent'"
            class="rounded-xl border border-green-500/30 bg-green-500/10 px-3.5 py-2.5 text-center text-xs font-medium text-green-600 dark:text-green-400"
        >
            A new 6-digit verification code and verification link have been sent
            to your email address.
        </div>

        <!-- 6-digit verification code form -->
        <Form
            v-bind="verifyCode.form()"
            class="space-y-4"
            reset-on-error
            @error="code = ''"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="code" :value="code" />

            <div
                class="flex flex-col items-center justify-center space-y-2 text-center"
            >
                <label for="email-verification-otp" class="sr-only">
                    6-digit verification code
                </label>
                <div class="flex w-full items-center justify-center">
                    <InputOTP
                        id="email-verification-otp"
                        v-model="code"
                        :maxlength="6"
                        :disabled="processing"
                        autofocus
                    >
                        <InputOTPGroup>
                            <InputOTPSlot
                                v-for="index in 6"
                                :key="index"
                                :index="index - 1"
                            />
                        </InputOTPGroup>
                    </InputOTP>
                </div>
                <InputError :message="errors.code" />
            </div>

            <Button
                type="submit"
                class="h-11 w-full"
                :disabled="processing || code.length !== 6"
                data-test="verify-email-code-button"
            >
                <Spinner v-if="processing" />
                <span>Verify code</span>
            </Button>
        </Form>

        <!-- Resend code & Logout -->
        <Form
            v-bind="send.form()"
            class="space-y-4 border-t border-border/70 pt-4 text-center"
            v-slot="{ processing }"
        >
            <Button
                type="submit"
                :disabled="processing"
                variant="secondary"
                class="h-10 w-full"
                data-test="resend-verification-code-button"
            >
                <Spinner v-if="processing" />
                <span>Resend verification code</span>
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Log out
            </TextLink>
        </Form>
    </div>
</template>
