<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import { index as cartIndex } from '@/routes/cart';
import { useCartPreview } from '@modules/cart/composables/useCartPreview';

const { itemCount, lastAdded, dialogOpen } = useCartPreview();
</script>

<template>
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Shopping Cart</DialogTitle>
                <DialogDescription>
                    Review the items selected during this browsing session.
                </DialogDescription>
            </DialogHeader>

            <div class="py-4">
                <div
                    v-if="itemCount > 0"
                    class="rounded-xl border border-border bg-secondary/40 p-4 text-sm"
                >
                    <p class="font-semibold text-foreground">
                        {{ itemCount }}
                        {{ itemCount === 1 ? 'item' : 'items' }}
                        selected in current session
                    </p>
                    <p
                        v-if="lastAdded"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Last added: {{ lastAdded.name }} ({{
                            lastAdded.priceFormatted
                        }})
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Your shopping cart is currently empty.
                </p>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="h-11"
                    @click="dialogOpen = false"
                >
                    Continue Shopping
                </Button>
                <Button as-child class="h-11">
                    <Link :href="cartIndex.url()" @click="dialogOpen = false">
                        View Full Cart
                    </Link>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
