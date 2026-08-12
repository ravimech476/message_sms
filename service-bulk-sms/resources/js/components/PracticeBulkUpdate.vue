<template>
    <div>
        <div class="absolute top-0 left-0 bg-opacity-75 bg-blue-900 h-full w-full z-10"></div>
        <div class="absolute inset-x-0 mx-auto bg-white border w-1/2 xl:w-1/3 rounded-lg p-10 shadow-lg z-20">
            <button class="absolute top-0 right-0 mt-5 mr-5" v-on:click="triggerModal()">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <h1 class="text-2xl font-semibold text-gray-700 mb-6">Bulk update credentials</h1>
            <div class="flex items-center py-2">
                <label class="w-1/3" :class="stpFilter !== '' ? 'text-gray-500' : ''">CCG</label>
                <select class="form-select w-full rounded-lg" v-model="ccgFilter" :disabled="stpFilter !== ''">
                    <option value="">All CCGs</option>
                    <option v-for="(name,code) in ccgList" v-bind:key="`ccg-${code}`" :value="code">{{ name }}</option>
                </select>
            </div>
            <div class="flex items-center py-2">
                <label class="w-1/3" :class="ccgFilter !== '' ? 'text-gray-500' : ''">STP</label>
                <select class="form-select w-full rounded-lg" v-model="stpFilter" :disabled="ccgFilter !== ''">
                    <option value="">All STPs</option>
                    <option v-for="(name,code) in stpList" v-bind:key="`stp-${code}`" :value="code">{{ name }}</option>
                </select>
            </div>
            <div v-if="ccgFilter || stpFilter">
                <div class="flex items-center py-2">
                    <label class="w-1/3">Provider</label>
                    <select class="form-select w-full rounded-lg" v-model="providerFilter" v-on:change="setCredentials">
                        <option value="">Pick a provider</option>
                        <option v-for="provider in providers.data" v-bind:key="`provider-${provider.driver}`" :value="provider.driver">{{ provider.name }}</option>
                    </select>
                </div>
                <div v-if="providerFilter && credentials">
                    <div v-for="(value, key) in credentials" v-bind:key="`credential-${providerFilter}-${key}`" class="flex items-center py-2">
                        <label class="w-1/3">{{ snakeToText(key) }}</label>
                        <input type="text" v-model="credentials[key]" class="form-input w-full" />
                    </div>
                </div>
                <div v-if="providerFilter" class="mt-4">
                    <div class="text-right">
                        <button v-if="!confirm && !loading" v-on:click="confirmUpdate" class="inline-flex items-center bg-green-500 text-white rounded px-3 py-2 hover:bg-gray-700 hover:text-gray-100 cursor-pointer">Update practices</button>
                        <div v-if="loading" class="inline-flex items-center bg-gray-300 text-gray-700 rounded px-3 py-2 ">
                            <svg class="animate-spin h-5 w-5 text-blue-800 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="text-blue-300" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Please wait...
                        </div>
                    </div>
                    <div v-if="confirm">
                        <div class="text-red-600 mb-4 text-center font-semibold">Are you sure you want to update these practices?<br> This action cannot be reversed.</div>
                        <div class="flex justify-between items-center">
                            <button v-on:click="cancelUpdate" class="inline-flex items-center mr-2 bg-red-500 text-white rounded px-3 py-2 hover:bg-gray-700 hover:text-gray-100 cursor-pointer">Cancel</button>
                            <button v-on:click="update" class="inline-flex items-center bg-green-500 text-white rounded px-3 py-2 hover:bg-gray-700 hover:text-gray-100 cursor-pointer">Yes, update</button>
                        </div>
                   </div>
                </div>
            </div>
            <div v-if="message" class="mt-4 bg-green-100 border border-green-300 p-2 rounded text-green-600 text-center">
                {{ message }}
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: ['ccgList', 'stpList', 'providers', 'bulkUpdateUri'],
    data() {
      return {
          ccgFilter: '',
          stpFilter: '',
          providerFilter: '',
          credentials: [],
          confirm: false,
          loading: false,
          message: null,
      }
    },
    computed: {
    },
    mounted() {
        this.ccgFilter = this.$parent.ccgFilter;
        this.stpFilter = this.$parent.stpFilter;
    },
    methods: {
        triggerModal() {
            this.$emit('trigger');
        },
        snakeToText(str) {
            return str.replace('_', ' ').replace(/\b\S/g, t => t.toUpperCase());
        },
        confirmUpdate() {
            this.confirm = true;
        },
        setCredentials() {
            this.credentials = Object.assign({}, this.providers.data[this.providerFilter].credentials);
            this.message = null;
        },
        update() {
            this.confirm = false;
            this.loading = true;
            axios
                .post(this.bulkUpdateUri, {
                    ccg: this.ccgFilter,
                    stp: this.stpFilter,
                    provider: this.providerFilter,
                    credentials: this.credentials,
                })
                .then(response => {
                    this.$emit('fetch');
                    this.loading = false;
                    this.credentials = [];
                    this.ccgFilter = this.$parent.ccgFilter;
                    this.stpFilter = this.$parent.stpFilter;
                    this.providerFilter = '';
                    this.message = `${response.data.data.length} practices has been updated`;
                });
        },
        cancelUpdate() {
            this.confirm = false;
        }
    }
}
</script>
