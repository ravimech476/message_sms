<template>
    <div>
        <practice-bulk-update v-if="showBulkModal" v-on:trigger="triggerBulkModal()" v-on:fetch="filterPractises()" :bulk-update-uri="bulkUpdateUri" :ccg-list="ccgList" :stp-list="stpList" :providers="providers"></practice-bulk-update>
        <practice-edit v-if="showEditModal" v-on:trigger="triggerEditModal()" v-on:fetch="filterPractises()" :practice="selectedPractice" :providers="providers" :provider-uri="providersUri"></practice-edit>
        <div class="flex justify-between items-center mb-4">
            <div>
                <h1 class="text-3xl font-semibold text-gray-700">Practices</h1>
            </div>
            <div>
                <a v-on:click="triggerBulkModal()" class="inline-block bg-indigo-500 text-white px-4 py-2 rounded hover:bg-gray-700 shadow cursor-pointer">Bulk update credentials</a>
            </div>
        </div>
        <div class="mb-4 -mx-4">
            <div class="flex">
                <div class="w-1/3 px-4 py-2">
                    <input placeholder="Search for practice or ODS code" class="form-input w-full rounded-lg" v-model="nameFilter" v-on:keyup="delayFilter()">
                </div>
                <div class="w-1/3 px-4 py-2">
                    <select class="form-select w-full rounded-lg" v-model="ccgFilter" v-on:change="filterPractises()">
                        <option value="">All CCGs</option>
                        <option v-for="(name,code) in ccgList" v-bind:key="`ccg-${code}`" :value="code">{{ name }}</option>
                    </select>
                </div>
                <div class="w-1/3 px-4 py-2">
                    <select class="form-select w-full rounded-lg" v-model="stpFilter" v-on:change="filterPractises()">
                        <option value="">All STPs</option>
                        <option v-for="(name,code) in stpList" v-bind:key="`stp-${code}`" :value="code">{{ name }}</option>
                    </select>
                </div>
                <div class="w-1/3 px-4 py-2">
                    <select class="form-select w-full rounded-lg" v-model="providerFilter" v-on:change="filterPractises()">
                        <option value="">All providers</option>
                        <option value="none">Without provider</option>
                        <option v-for="(name,driver) in providerList" v-bind:key="`provider-${driver}`" :value="driver">{{ name }}</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="mb-4 text-sm text-gray-600 inline-flex justify-between items-center overflow-hidden">
            <div><span class="font-bold">{{ total }}</span> practice(s) matching with the filter</div>
            <div v-if="loading" class="ml-4">
                <svg class="animate-spin h-5 w-5 text-blue-800" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="text-blue-300" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        <div class="relative">
            <div v-if="loading" class="w-full h-full opacity-75 bg-white absolute top-0"></div>
            <div class="shadow overflow-hidden border-b border-gray-200 rounded-lg">
                <table class="table table-fixed w-full text-sm bg-white">
                    <thead>
                    <tr>
                        <td class="w-4/12 bg-gray-200 px-4 py-2 font-medium tracking-wider text-xs uppercase">Practice Name</td>
                        <td class="w-5/12 bg-gray-200 px-4 py-2 font-medium tracking-wider text-xs uppercase">CCG and STP</td>
                        <td class="w-2/12 bg-gray-200 px-4 py-2 font-medium tracking-wider text-xs uppercase">Provider</td>
                        <td class="w-1/12 bg-gray-200 px-4 py-2 font-medium tracking-wider text-xs uppercase"></td>
                    </tr>
                    </thead>
                    <practice-row v-for="practice in practices.data" v-on:edit="editPractice" v-bind:key="`practice-${practice.id}`" :practice="practice"></practice-row>
                </table>
            </div>
        </div>
        <div class="mt-2 flex py-4">
            <div class="w-1/2">
                <button v-if="showPrevious" v-on:click="back()" class="inline-flex items-center bg-gray-300 rounded px-3 py-2 hover:bg-gray-600 hover:text-gray-100 cursor-pointer">
                    <svg class="fill-current w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Previous page
                </button>
            </div>
            <div class="w-1/2 text-right">
                <button v-if="showNext" v-on:click="next()" class="inline-flex items-center bg-gray-300 rounded px-3 py-2 hover:bg-gray-600 hover:text-gray-100 cursor-pointer">
                    Next page
                    <svg class="fill-current w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import PracticeRow from "./PracticeRow";
export default {
    components: {PracticeRow},
    props: ['practicesUri', 'providersUri', 'bulkUpdateUri', 'ccgList', 'stpList', 'providerList'],
    data() {
      return {
          practices: [],
          providers: [],
          nameFilter: null,
          ccgFilter: '',
          stpFilter: '',
          providerFilter: '',
          currentPage: 1,
          lastPage: 1,
          timer: null,
          loading: false,
          showBulkModal: false,
          showEditModal: false,
          selectedPractice: null,
      }
    },
    computed: {
        total() {
            return this.practices.meta ? this.practices.meta.total : 0;
        },
        showNext() {
            return this.lastPage > this.currentPage;
        },
        showPrevious() {
            return this.currentPage !== 1;
        },
    },
    mounted() {
        this.fetch();
    },
    methods: {
        editPractice(practice) {
          this.selectedPractice = practice;
          this.triggerEditModal();
        },
        triggerBulkModal() {
            this.showBulkModal = !this.showBulkModal;
        },
        triggerEditModal() {
            this.showEditModal = !this.showEditModal;
        },
        delayFilter() {
            if (this.timer) {
                clearTimeout(this.timer);
                this.timer = null;
            }
            this.timer = setTimeout(() => {
                this.filterPractises();
            }, 1000);
        },
        next() {
            if (this.showNext) {
                this.currentPage++;
                this.fetch();
            }
        },
        back() {
            if (this.showPrevious) {
                this.currentPage--;
                this.fetch();
            }
        },
        fetchPractises() {
            return axios.get(this.practicesUri, {
                params: {
                    name: this.nameFilter,
                    ccg: this.ccgFilter,
                    stp: this.stpFilter,
                    provider: this.providerFilter,
                    hasDomain: true,
                    page: this.currentPage,
                }
            });
        },
        fetchProviders() {
            return axios.get(this.providersUri);
        },
        fetch() {
            this.loading = true;
            axios.all([this.fetchPractises(), this.fetchProviders()]).then(axios.spread((...responses) => {
                this.practices = responses[0].data;
                this.currentPage = responses[0].data.meta.current_page;
                this.lastPage = responses[0].data.meta.last_page;
                this.providers = responses[1].data;
                this.loading = false;
            })).catch(errors => {
                this.loading = false;
            })
        },
        filterPractises() {
            this.loading = true;
            this.fetchPractises().then(response => {
                this.practices = response.data;
                this.currentPage = response.data.meta.current_page;
                this.lastPage = response.data.meta.last_page;
                this.loading = false;
            }).catch(errors => {
                this.loading = false;
            })
        }
    }
}
</script>
