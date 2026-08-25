<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    vehicles: { type: Array, default: () => [] },
    bookings: { type: Array, default: () => [] },
    blocks: { type: Array, default: () => [] },
    reasons: { type: Object, default: () => ({}) },
    canWrite: { type: Boolean, default: true },
});

const days = computed(() => Array.from({ length: props.month.days }, (_, i) => i + 1));

const dateOf = (day) => `${props.month.value}-${String(day).padStart(2, '0')}`;

const covers = (item, day) => {
    const d = dateOf(day);
    return item.start_date <= d && item.end_date >= d;
};

/* Une case porte au plus une occupation : le moteur de disponibilité interdit
   déjà le chevauchement, donc la première trouvée est la bonne. */
const cellFor = (vehicleId, day) => {
    const booking = props.bookings.find((b) => b.vehicle_id === vehicleId && covers(b, day));
    if (booking) {
        return {
            type: 'booking',
            item: booking,
            classes: booking.status === 'in_progress' ? 'bg-indigo-500' : 'bg-green-500',
            title: `${booking.reference} — ${booking.client_name}`,
        };
    }

    const block = props.blocks.find((b) => b.vehicle_id === vehicleId && covers(b, day));
    if (block) {
        return {
            type: 'block',
            item: block,
            classes: 'bg-gray-400',
            title: `${props.reasons[block.reason] ?? block.reason}${block.note ? ' — ' + block.note : ''}`,
        };
    }

    return null;
};

const isWeekend = (day) => {
    // Semaine algérienne : le week-end tombe vendredi et samedi.
    const weekday = new Date(`${dateOf(day)}T12:00:00`).getDay();
    return weekday === 5 || weekday === 6;
};

const goToMonth = (value) => router.get(route('agency.calendar'), { month: value + '-01' });

const blocking = ref(false);
const blockForm = useForm({
    vehicle_id: '',
    start_date: '',
    end_date: '',
    reason: 'maintenance',
    note: '',
});

const submitBlock = () => {
    blockForm.post(route('agency.blocks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            blocking.value = false;
            blockForm.reset();
        },
    });
};

const removeBlock = (block) => {
    router.delete(route('agency.blocks.destroy', block.id), { preserveScroll: true });
};

const selected = ref(null);
</script>

<template>
    <Head title="Calendrier" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Calendrier</h2>
                <button v-if="canWrite" @click="blocking = true"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Bloquer une période
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.errors?.block"
                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    {{ $page.props.errors.block }}
                </div>

                <div class="flex items-center justify-between rounded-lg bg-white p-3 shadow-sm">
                    <button @click="goToMonth(month.previous)"
                        class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">
                        ← Mois précédent
                    </button>
                    <p class="font-semibold capitalize text-gray-900">{{ month.label }}</p>
                    <button @click="goToMonth(month.next)"
                        class="rounded px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">
                        Mois suivant →
                    </button>
                </div>

                <div v-if="!vehicles.length" class="rounded-lg bg-white p-10 text-center shadow-sm">
                    <p class="font-medium text-gray-900">Aucun véhicule en ligne</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Le calendrier affiche les véhicules publiés ou en modération.
                    </p>
                    <Link :href="route('agency.vehicles.index')"
                        class="mt-4 inline-block rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Mes véhicules
                    </Link>
                </div>

                <div v-else class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full border-collapse text-xs">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-semibold text-gray-700">
                                    Véhicule
                                </th>
                                <th v-for="day in days" :key="day"
                                    class="w-6 border-l border-gray-100 px-0 py-2 text-center font-medium"
                                    :class="isWeekend(day) ? 'bg-gray-50 text-gray-400' : 'text-gray-500'">
                                    {{ day }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="vehicle in vehicles" :key="vehicle.id" class="border-t border-gray-100">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 font-medium text-gray-800">
                                    {{ vehicle.title }}
                                </td>
                                <td v-for="day in days" :key="day"
                                    class="border-l border-gray-100 p-0"
                                    :class="isWeekend(day) ? 'bg-gray-50' : ''">
                                    <button v-if="cellFor(vehicle.id, day)"
                                        @click="selected = cellFor(vehicle.id, day)"
                                        :title="cellFor(vehicle.id, day).title"
                                        class="block h-7 w-full"
                                        :class="cellFor(vehicle.id, day).classes" />
                                    <span v-else class="block h-7 w-full" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-4 text-xs text-gray-600">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-green-500" /> Réservation confirmée</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-indigo-500" /> Location en cours</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm bg-gray-400" /> Blocage manuel</span>
                    <span class="ml-auto text-gray-400">Vendredi et samedi grisés</span>
                </div>
            </div>
        </div>

        <!-- Détail d'une case -->
        <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="selected = null">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <template v-if="selected.type === 'booking'">
                    <h3 class="font-semibold text-gray-900">{{ selected.item.reference }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ selected.item.client_name }}</p>
                    <p class="text-sm text-gray-500">
                        Du {{ selected.item.start_date }} au {{ selected.item.end_date }}
                    </p>
                    <Link :href="route('agency.bookings.show', selected.item.id)"
                        class="mt-4 inline-block rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                        Ouvrir la réservation
                    </Link>
                </template>

                <template v-else>
                    <h3 class="font-semibold text-gray-900">{{ reasons[selected.item.reason] }}</h3>
                    <p v-if="selected.item.note" class="mt-1 text-sm text-gray-600">{{ selected.item.note }}</p>
                    <p class="text-sm text-gray-500">
                        Du {{ selected.item.start_date }} au {{ selected.item.end_date }}
                    </p>
                    <button v-if="canWrite" @click="removeBlock(selected.item); selected = null"
                        class="mt-4 rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                        Lever le blocage
                    </button>
                </template>

                <button @click="selected = null" class="mt-4 block text-sm text-gray-500 hover:text-gray-900">
                    Fermer
                </button>
            </div>
        </div>

        <!-- Nouveau blocage -->
        <div v-if="blocking" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="blocking = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="font-semibold text-gray-900">Bloquer une période</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Le véhicule cesse d’apparaître sur ces dates : maintenance, ou location
                    conclue hors plateforme.
                </p>

                <div class="mt-4 space-y-3 text-sm">
                    <select v-model="blockForm.vehicle_id"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Choisir un véhicule…</option>
                        <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.title }}</option>
                    </select>
                    <p v-if="blockForm.errors.vehicle_id" class="text-red-600">{{ blockForm.errors.vehicle_id }}</p>

                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="blockForm.start_date" type="date"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <input v-model="blockForm.end_date" type="date" :min="blockForm.start_date"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <p v-if="blockForm.errors.end_date" class="text-red-600">{{ blockForm.errors.end_date }}</p>

                    <select v-model="blockForm.reason"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="(label, key) in reasons" :key="key" :value="key">{{ label }}</option>
                    </select>

                    <input v-model="blockForm.note" type="text" placeholder="Précision (facultatif)"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button @click="blocking = false"
                        class="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button @click="submitBlock" :disabled="blockForm.processing"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        Bloquer
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
