<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useCommunes } from '@/composables/useCommunes';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    wilayas: { type: Array, default: () => [] },
});

const form = useForm({
    commercial_name: '',
    manager_name: '',
    trade_register_number: '',
    nif: '',
    wilaya_id: '',
    commune_id: '',
    address: '',
    phone: '',
    whatsapp: '',
    email: '',
    password: '',
    password_confirmation: '',
    description: '',
    logo: null,
    trade_register_file: null,
});

// Les communes sont chargées à la demande : le pays en compte 1541, et les
// livrer toutes avec le formulaire les ferait payer à chaque inscription.
const wilayaId = computed(() => form.wilaya_id);
const { communes, loading: loadingCommunes } = useCommunes(wilayaId, props.wilayas, {
    // La commune retenue n'appartient plus à la wilaya choisie : on la vide
    // plutôt que de laisser une incohérence que seul le serveur rejetterait.
    onChange: () => (form.commune_id = ''),
});

const submit = () => {
    form.post(route('agency.register'), {
        forceFormData: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};
</script>

<template>
    <Head title="Inscrire mon agence" />

    <GuestLayout>
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Inscrire mon agence</h1>
            <p class="mt-2 text-sm text-gray-600">
                Votre demande est vérifiée par notre équipe sous 48&nbsp;heures ouvrées.
                Le registre de commerce nous sert à confirmer l'existence de l'agence.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <section class="space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    L'agence
                </h2>

                <div>
                    <InputLabel for="commercial_name" value="Nom commercial" />
                    <TextInput id="commercial_name" v-model="form.commercial_name" type="text"
                        class="mt-1 block w-full" required autofocus />
                    <InputError class="mt-2" :message="form.errors.commercial_name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="trade_register_number" value="N° de registre de commerce" />
                        <TextInput id="trade_register_number" v-model="form.trade_register_number"
                            type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="form.errors.trade_register_number" />
                    </div>
                    <div>
                        <InputLabel for="nif" value="NIF (facultatif)" />
                        <TextInput id="nif" v-model="form.nif" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.nif" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="wilaya_id" value="Wilaya" />
                        <select id="wilaya_id" v-model="form.wilaya_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Choisir —</option>
                            <option v-for="w in wilayas" :key="w.id" :value="w.id">
                                {{ w.code }} — {{ w.name_fr }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.wilaya_id" />
                    </div>
                    <div>
                        <InputLabel for="commune_id" value="Commune" />
                        <select id="commune_id" v-model="form.commune_id" required :disabled="!form.wilaya_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                            <option value="">
                                {{ loadingCommunes ? 'Chargement…'
                                    : form.wilaya_id ? '— Choisir —' : 'Choisissez d’abord une wilaya' }}
                            </option>
                            <option v-for="c in communes" :key="c.id" :value="c.id">{{ c.name_fr }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.commune_id" />
                    </div>
                </div>

                <div>
                    <InputLabel for="address" value="Adresse" />
                    <TextInput id="address" v-model="form.address" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.address" />
                </div>

                <div>
                    <InputLabel for="description" value="Présentation (facultatif)" />
                    <textarea id="description" v-model="form.description" rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Votre flotte, vos points forts, vos conditions particulières…"></textarea>
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
            </section>

            <section class="space-y-4 border-t border-gray-200 pt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Le gérant et le contact
                </h2>

                <div>
                    <InputLabel for="manager_name" value="Nom du gérant" />
                    <TextInput id="manager_name" v-model="form.manager_name" type="text"
                        class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.manager_name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="phone" value="Téléphone" />
                        <TextInput id="phone" v-model="form.phone" type="tel" class="mt-1 block w-full"
                            placeholder="05 55 12 34 56" required />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                    <div>
                        <InputLabel for="whatsapp" value="WhatsApp (facultatif)" />
                        <TextInput id="whatsapp" v-model="form.whatsapp" type="tel" class="mt-1 block w-full"
                            placeholder="05 55 12 34 56" />
                        <InputError class="mt-2" :message="form.errors.whatsapp" />
                    </div>
                </div>

                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full"
                        required autocomplete="username" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="password" value="Mot de passe" />
                        <TextInput id="password" v-model="form.password" type="password"
                            class="mt-1 block w-full" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirmation" />
                        <TextInput id="password_confirmation" v-model="form.password_confirmation"
                            type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>
                </div>
            </section>

            <section class="space-y-4 border-t border-gray-200 pt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Documents
                </h2>

                <div>
                    <InputLabel for="trade_register_file" value="Registre de commerce" />
                    <input id="trade_register_file" type="file" accept=".pdf,.jpg,.jpeg,.png" required
                        @input="form.trade_register_file = $event.target.files[0]"
                        class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                    <p class="mt-1 text-xs text-gray-500">
                        PDF ou image, 10&nbsp;Mo maximum. Ce document reste confidentiel : seul
                        l'administrateur y a accès, il ne sera jamais publié.
                    </p>
                    <InputError class="mt-2" :message="form.errors.trade_register_file" />
                </div>

                <div>
                    <InputLabel for="logo" value="Logo (facultatif)" />
                    <input id="logo" type="file" accept="image/*"
                        @input="form.logo = $event.target.files[0]"
                        class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-100" />
                    <p class="mt-1 text-xs text-gray-500">Image, 5&nbsp;Mo maximum. Il apparaîtra sur vos annonces.</p>
                    <InputError class="mt-2" :message="form.errors.logo" />
                </div>
            </section>

            <div class="flex items-center justify-between border-t border-gray-200 pt-6">
                <Link :href="route('login')" class="text-sm text-gray-600 underline hover:text-gray-900">
                    J'ai déjà un compte
                </Link>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Envoyer ma demande
                </PrimaryButton>
            </div>

            <p v-if="form.progress" class="text-sm text-gray-600">
                Envoi des fichiers… {{ form.progress.percentage }}&nbsp;%
            </p>
        </form>
    </GuestLayout>
</template>
