<template>
    <div>
        <div class="absolute top-0 left-0 bg-opacity-75 bg-blue-900 h-full w-full z-10"></div>
        <div class="absolute inset-x-0 mx-auto bg-white border w-1/2 xl:w-1/3 rounded-lg p-10 shadow-lg z-20">
            <button class="absolute top-0 right-0 mt-5 mr-5" v-on:click="triggerModal()">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div class="text-gray-600 font-medium tracking-wider text-xs mb-1 uppercase">Edit credentials</div>
            <h1 class="text-xl font-semibold text-gray-700 mb-6 mr-10 leading-tight">{{ practice.practice_name }}</h1>
            <div v-if="message" class="mb-4 bg-green-100 border border-green-300 p-2 rounded text-green-600 text-center">
                {{ message }}
            </div>
            <div>
                <div class="flex items-center py-2">
                    <label class="w-1/3">Provider</label>
                    <select name="sp_sms_provider" class="form-select w-full" v-model="selectedProvider">
                        <option value="">Service disabled</option>
                        <option v-for="provider in providers.data" :value="provider.driver" :selected="provider.driver === selectedProvider">{{ provider.name }}</option>
                    </select>
                </div>
                <div v-if="selectedProvider && providers.data[selectedProvider]">
                    <div v-for="(value, key) in providers.data[selectedProvider].credentials" v-bind:key="`credential-${selectedProvider}-${key}`" class="flex items-center py-2">
                        <label class="w-1/3">{{ snakeToText(key) }}</label>
                        <input type="text" v-model="credentials[key]" class="form-input w-full" />
                    </div>
                </div>
                <div class="mt-4 text-right">
                    <button v-if="!loading" v-on:click="updateProvider" class="inline-flex items-center bg-green-500 text-white rounded px-3 py-2 hover:bg-gray-700 hover:text-gray-100 cursor-pointer">Update details</button>
                    <div v-if="loading" class="inline-flex items-center bg-gray-300 text-gray-700 rounded px-3 py-2 ">
                        <svg class="animate-spin h-5 w-5 text-blue-800 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="text-blue-300" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Please wait...
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['practice', 'providers'],
    data() {
        return {
            loading: false,
            selectedProvider: '',
            credentials: [],
            message: null,
        }
    },
    computed: {
        providerFields() {
            return this.providers && this.selectedProvider ? Object.keys(this.providers.data[this.selectedProvider].credentials) : [];
        },
    },
    mounted() {
        this.selectedProvider = this.practice.provider ? this.practice.provider.driver : '';

        if (this.selectedProvider && this.practice.credentials[this.selectedProvider]) {
            this.credentials = this.practice.credentials[this.selectedProvider];
        }
    },
    watch: {
        selectedProvider: function(newValue, oldValue) {
            if (newValue !== oldValue) {
                if (this.practice.credentials && this.practice.credentials.hasOwnProperty(newValue)) {
                    this.credentials = this.practice.credentials[newValue];
                } else {
                    this.credentials = this.providers.data[newValue].credentials;
                }
            }
        }
    },
    methods: {
        updateProvider() {
            this.loading = true;
            axios
                .post(this.providers.meta.update, {
                    domain: this.practice.domain,
                    provider: this.selectedProvider,
                    credentials: this.selectedProvider ? this.credentials : [],
                    make_default: true,
                })
                .then(response => {
                    this.$emit('fetch');
                    this.loading = false;
                    this.message = `Credentials has been updated`;
                });
        },
        snakeToText: (str) => str.replace('_', ' ').replace(/\b\S/g, t => t.toUpperCase()),
        triggerModal() {
            this.$emit('trigger');
        },
    }
}
</script>
