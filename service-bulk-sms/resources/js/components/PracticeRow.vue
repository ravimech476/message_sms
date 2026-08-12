<template>
    <tr>
        <td class="border px-4 py-3">
            <div class="font-semibold mb-1">{{ practice.practice_name }}</div>
            <div class="text-xs text-gray-600">ODS code: {{ practice.ods }}</div>
        </td>
        <td class="border px-4 py-3">
            <div class="mb-1"><span class="inline-block px-1 mr-2 text-xs font-semibold bg-green-200 text-green-800 rounded">CCG</span> {{ practice.ccg.name }} ({{ practice.ccg.code }})</div>
            <div><span class="inline-block px-1 mr-2 text-xs font-semibold border border-green-300 text-gray-800 rounded">STP</span> {{ practice.stp.name }} ({{ practice.stp.code }})</div>
        </td>
        <td class="border px-4 py-3">
            <div>{{ provider ? provider : '-' }}</div>
            <div v-if="provider" class="text-xs text-gray-600"><strong>{{ credentials }}</strong> credential(s)</div>
        </td>
        <td class="border px-4 py-3 text-center"><button v-on:click="edit" class="inline-block bg-indigo-500 text-white text-xs px-4 py-2 rounded hover:bg-gray-700 shadow">Edit</button></td>
    </tr>
</template>

<script>
export default {
    props: ['practice'],
    computed: {
        provider() {
            return this.practice.provider ? this.practice.provider.name : null;
        },
        credentials() {
            return this.practice.credentials && this.practice.provider ? Object.keys(this.practice.credentials[this.practice.provider.driver]).length : [];
        }
    },
    mounted() {
    },
    methods: {
        edit() {
            this.$emit('edit', this.practice);
        },
    }
}
</script>
