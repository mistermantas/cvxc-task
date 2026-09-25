<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Customer = {
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    birth_date: string | null;
    country: string;
};
type Row = { line: number; data: Customer; errors: string[] };
type Preview = { total: number; valid: number; failed: number; rows: Row[] };
type Summary = { total: number; imported: number; failed: number };

const props = defineProps<{
    preview: Preview | null;
    filename: string | null;
    summary: Summary | null;
}>();

const page = usePage();
const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const selectedName = ref('');
const saving = ref(false);
const form = useForm<{ file: File | null }>({ file: null });
const errors = computed(() => page.props.errors as Record<string, string>);

function chooseFile(file?: File) {
    if (!file) return;
    form.file = file;
    selectedName.value = file.name;
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    chooseFile(event.dataTransfer?.files[0]);
}

function upload() {
    if (!form.file) return;
    form.post('/imports/preview', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            selectedName.value = '';
            form.reset();
        },
    });
}

function save() {
    saving.value = true;
    router.post(
        '/imports',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function cancel() {
    router.post('/imports/cancel', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Klientų importas" />
    <div class="min-h-screen bg-[#f6f6f2] text-[#18322f]">
        <header class="bg-[#173b36] text-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-9"
            >
                <div class="flex items-center gap-3.5">
                    <span
                        class="flex size-9 items-center justify-center rounded-lg border border-white/35 text-sm font-bold tracking-tight"
                        >RV</span
                    >
                    <span
                        class="text-sm font-semibold tracking-[0.12em] uppercase"
                        >Transport
                        <span class="font-normal text-white/65"
                            >/ Data</span
                        ></span
                    >
                </div>
                <span
                    class="hidden text-xs font-medium tracking-[0.18em] text-white/65 uppercase sm:block"
                    >Customer operations</span
                >
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-5 pt-12 pb-20 sm:px-9 sm:pt-12">
            <div
                class="mb-8 flex flex-wrap items-end justify-between gap-6 border-b border-[#d6ded8] pb-8"
            >
                <div>
                    <div
                        class="mb-4 flex items-center gap-2 text-[11px] font-bold tracking-[0.2em] text-[#3b746a] uppercase"
                    >
                        <span
                            class="inline-block size-1.5 rounded-full bg-[#3b746a]"
                        ></span>
                        Importas / 01
                    </div>
                    <h1
                        class="text-[2.45rem] leading-[1.05] font-semibold tracking-[-0.055em] sm:text-[3.65rem]"
                    >
                        Klientų duomenys
                    </h1>
                    <p
                        class="mt-4 max-w-xl text-sm leading-6 text-[#58716c] sm:text-base"
                    >
                        Įkelkite CSV, patikrinkite eilutes ir išsaugokite
                        tinkamus įrašus.
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 text-xs font-medium text-[#58716c]"
                >
                    <span
                        class="flex size-7 items-center justify-center rounded-full bg-[#256e62] text-white"
                        >1</span
                    >
                    <span>Įkelti</span>
                    <span class="mx-1 h-px w-7 bg-[#b9ccc4]"></span>
                    <span
                        :class="[
                            'flex size-7 items-center justify-center rounded-full',
                            preview
                                ? 'bg-[#256e62] text-white'
                                : 'border border-[#b9ccc4]',
                        ]"
                        >2</span
                    >
                    <span>Patikrinti</span>
                </div>
            </div>

            <div
                v-if="summary"
                class="mb-8 grid grid-cols-3 gap-x-3 gap-y-2 rounded-xl border border-[#afd2c3] bg-[#e8f3ec] px-5 py-4 text-sm sm:flex sm:flex-wrap sm:items-center sm:gap-x-8"
                role="status"
            >
                <span class="col-span-3 font-semibold text-[#15584c]"
                    >Importas baigtas</span
                >
                <span
                    >Iš viso <strong>{{ summary.total }}</strong></span
                >
                <span
                    >Įkelta <strong>{{ summary.imported }}</strong></span
                >
                <span
                    >Praleista <strong>{{ summary.failed }}</strong></span
                >
            </div>

            <section
                v-if="!preview"
                class="rounded-2xl border border-[#d8e0d9] bg-white shadow-[0_14px_40px_rgba(21,55,48,0.04)]"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e4ebe5] px-5 py-5 sm:px-7"
                >
                    <h2 class="text-lg font-semibold tracking-[-0.025em]">
                        CSV failas
                    </h2>
                    <a
                        href="/samples/customers.csv"
                        download
                        class="text-sm font-medium text-[#246f61] underline decoration-[#9ccabb] underline-offset-4 hover:text-[#174d43]"
                        >Atsisiųsti pavyzdį ↗</a
                    >
                </div>
                <div class="p-5 sm:p-7">
                    <div
                        :class="[
                            'flex min-h-44 flex-col items-center justify-center rounded-xl border-2 border-dashed px-5 py-6 text-center transition-colors',
                            dragging
                                ? 'border-[#247363] bg-[#edf7f1]'
                                : 'border-[#bfd2c6] bg-[#f8faf7]',
                        ]"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <div
                            class="mb-4 flex size-12 items-center justify-center rounded-xl bg-[#e3eee8] text-[#246f61]"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                class="size-6"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M12 16V4m0 0-4 4m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"
                                />
                            </svg>
                        </div>
                        <strong class="text-base font-semibold">{{
                            selectedName || 'Įmeskite CSV failą čia'
                        }}</strong>
                        <span class="mt-1 text-sm text-[#71877e]">arba</span>
                        <button
                            type="button"
                            class="mt-4 rounded-lg border border-[#a9c9b9] bg-white px-4 py-2 text-sm font-semibold text-[#246f61] shadow-sm hover:bg-[#edf6f0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#246f61]"
                            @click="fileInput?.click()"
                        >
                            Pasirinkti failą
                        </button>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            class="sr-only"
                            aria-label="CSV failas"
                            @change="
                                chooseFile(
                                    ($event.target as HTMLInputElement)
                                        .files?.[0],
                                )
                            "
                        />
                    </div>
                    <div
                        class="mt-5 flex flex-wrap items-center justify-between gap-4"
                    >
                        <p class="text-xs text-[#71877e]">
                            UTF-8 · iki 2 MB · iki 1000 eilučių
                        </p>
                        <button
                            type="button"
                            :disabled="!form.file || form.processing"
                            class="rounded-lg bg-[#226d5f] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#19584d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#246f61] disabled:cursor-not-allowed disabled:opacity-40"
                            @click="upload"
                        >
                            {{
                                form.processing
                                    ? 'Tikrinama…'
                                    : 'Peržiūrėti duomenis'
                            }}
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>
                    <p
                        v-if="errors.file"
                        class="mt-4 rounded-lg bg-[#fff1ee] px-4 py-3 text-sm font-medium text-[#a74639]"
                        role="alert"
                    >
                        {{ errors.file }}
                    </p>
                </div>
            </section>

            <section
                v-if="preview"
                class="rounded-2xl border border-[#d8e0d9] bg-white shadow-[0_14px_40px_rgba(21,55,48,0.04)]"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-5 border-b border-[#e4ebe5] px-5 py-5 sm:px-7"
                >
                    <div>
                        <div class="flex items-center gap-3">
                            <h2
                                class="text-lg font-semibold tracking-[-0.025em]"
                            >
                                Peržiūra
                            </h2>
                            <span
                                class="max-w-[12rem] truncate rounded-md bg-[#f1f4f0] px-2 py-1 text-xs text-[#58716c] sm:max-w-xs"
                                >{{ filename }}</span
                            >
                        </div>
                        <p class="mt-1 text-sm text-[#71877e]">
                            Klaidingos eilutės bus praleistos.
                        </p>
                    </div>
                    <div class="flex gap-5 text-sm">
                        <span
                            ><strong class="text-[#226d5f]">{{
                                preview.valid
                            }}</strong>
                            tinkamos</span
                        >
                        <span
                            ><strong class="text-[#b85a48]">{{
                                preview.failed
                            }}</strong>
                            klaidingos</span
                        >
                    </div>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table
                        class="w-full min-w-[900px] border-collapse text-left text-sm"
                    >
                        <thead
                            class="bg-[#f9fbf8] text-xs font-semibold text-[#627a71]"
                        >
                            <tr>
                                <th class="px-7 py-4">Eil.</th>
                                <th class="px-4 py-4">Vardas, pavardė</th>
                                <th class="px-4 py-4">El. paštas</th>
                                <th class="px-4 py-4">Telefonas</th>
                                <th class="px-4 py-4">Gimimo data</th>
                                <th class="px-4 py-4">Šalis</th>
                                <th class="px-4 py-4">Būsena</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#edf0eb]">
                            <tr
                                v-for="row in preview.rows"
                                :key="row.line"
                                :class="row.errors.length ? 'bg-[#fffaf8]' : ''"
                            >
                                <td class="px-7 py-4 text-[#8ba098]">
                                    {{ row.line }}
                                </td>
                                <td class="px-4 py-4 font-medium">
                                    {{ row.data.first_name }}
                                    {{ row.data.last_name }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ row.data.email || '—' }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ row.data.phone || '—' }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ row.data.birth_date || '—' }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ row.data.country || '—' }}
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        :class="[
                                            'inline-block rounded-full px-2.5 py-1 text-xs font-semibold',
                                            row.errors.length
                                                ? 'bg-[#fff0e9] text-[#ae5945]'
                                                : 'bg-[#e9f4ec] text-[#246f61]',
                                        ]"
                                        >{{
                                            row.errors.length
                                                ? 'Klaida'
                                                : 'Tinkama'
                                        }}</span
                                    ><span
                                        v-if="row.errors.length"
                                        class="mt-2 block max-w-56 text-xs leading-5 text-[#a45c4f]"
                                        >{{ row.errors.join(' ') }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-[#edf0eb] md:hidden">
                    <details
                        v-for="row in preview.rows"
                        :key="row.line"
                        class="group px-5 py-4"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-start justify-between gap-3 [&::-webkit-details-marker]:hidden"
                        >
                            <span
                                ><span class="mr-2 text-xs text-[#8ba098]">{{
                                    row.line
                                }}</span
                                ><strong class="text-sm"
                                    >{{ row.data.first_name }}
                                    {{ row.data.last_name }}</strong
                                ><span
                                    class="mt-1 block pl-7 text-xs text-[#627a71]"
                                    >{{
                                        row.data.email || 'El. pašto nėra'
                                    }}</span
                                ><span
                                    v-if="row.errors.length"
                                    class="mt-1 block pl-7 text-xs leading-4 text-[#a45c4f]"
                                    >{{ row.errors[0] }}</span
                                ></span
                            >
                            <span class="flex shrink-0 items-center gap-1.5">
                                <span
                                    :class="[
                                        'rounded-full px-2 py-1 text-xs font-semibold',
                                        row.errors.length
                                            ? 'bg-[#fff0e9] text-[#ae5945]'
                                            : 'bg-[#e9f4ec] text-[#246f61]',
                                    ]"
                                    >{{
                                        row.errors.length ? 'Klaida' : 'Tinkama'
                                    }}</span
                                >
                                <svg
                                    class="size-3 text-[#8ba098] transition-transform group-open:rotate-180"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    aria-hidden="true"
                                >
                                    <path d="m3 6 5 5 5-5" />
                                </svg>
                            </span>
                        </summary>
                        <div
                            class="mt-4 grid grid-cols-2 gap-3 border-t border-[#edf0eb] pt-4 text-xs"
                        >
                            <span
                                >Telefonas<br /><strong>{{
                                    row.data.phone || '—'
                                }}</strong></span
                            ><span
                                >Gimimo data<br /><strong>{{
                                    row.data.birth_date || '—'
                                }}</strong></span
                            ><span
                                >Šalis<br /><strong>{{
                                    row.data.country || '—'
                                }}</strong></span
                            ><span
                                v-if="row.errors.length"
                                class="col-span-2 text-[#a45c4f]"
                                >{{ row.errors.join(' ') }}</span
                            >
                        </div>
                    </details>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-[#e4ebe5] px-5 py-5 sm:px-7"
                >
                    <button
                        type="button"
                        class="text-sm font-medium text-[#627a71] underline underline-offset-4 hover:text-[#18322f]"
                        @click="cancel"
                    >
                        Įkelti kitą failą
                    </button>
                    <button
                        type="button"
                        :disabled="!preview.valid || saving"
                        class="rounded-lg bg-[#226d5f] px-5 py-3 text-sm font-semibold text-white hover:bg-[#19584d] disabled:cursor-not-allowed disabled:opacity-40"
                        @click="save"
                    >
                        {{
                            saving
                                ? 'Išsaugoma…'
                                : `Importuoti ${preview.valid}`
                        }}
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
            </section>
        </main>
    </div>
</template>
