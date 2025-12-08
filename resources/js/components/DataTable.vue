<script setup lang="ts">
import { ref, computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { ChevronUp, ChevronDown, ChevronsLeft, ChevronsRight, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export interface Column {
    key: string;
    title?: string;
    label?: string;
    sortable?: boolean;
    render?: (value: any, row: any) => any;
}

interface Props {
    headers?: Column[];
    columns?: Column[];
    items?: any[];
    data?: any[];
    showSelect?: boolean;
    selectable?: boolean;
    itemsPerPage?: number;
    currentPage?: number;
    totalItems?: number;
    onPageChange?: (page: number) => void;
    onItemsPerPageChange?: (items: number) => void;
    onSort?: (column: string, direction: 'asc' | 'desc') => void;
    onSelectionChange?: (selectedRows: any[]) => void;
}

const props = withDefaults(defineProps<Props>(), {
    showSelect: true,
    selectable: true,
    itemsPerPage: 5,
    currentPage: 1,
    totalItems: 0,
});

// Support both 'headers' and 'columns' for compatibility
const columns = computed(() => props.headers || props.columns || []);
// Support both 'items' and 'data' for compatibility
const tableData = computed(() => props.items || props.data || []);
const isSelectable = computed(() => props.showSelect || props.selectable);

const selectedRows = ref<Set<number>>(new Set());
const sortColumn = ref<string | null>(null);
const sortDirection = ref<'asc' | 'desc'>('asc');

const allSelected = computed(() => {
    return tableData.value.length > 0 && selectedRows.value.size === tableData.value.length;
});

const someSelected = computed(() => {
    return selectedRows.value.size > 0 && selectedRows.value.size < tableData.value.length;
});

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedRows.value.clear();
    } else {
        tableData.value.forEach((row, index) => {
            selectedRows.value.add(index);
        });
    }
    emitSelectionChange();
};

const toggleSelectRow = (index: number) => {
    if (selectedRows.value.has(index)) {
        selectedRows.value.delete(index);
    } else {
        selectedRows.value.add(index);
    }
    emitSelectionChange();
};

const emitSelectionChange = () => {
    if (props.onSelectionChange) {
        const selected = Array.from(selectedRows.value).map(i => tableData.value[i]);
        props.onSelectionChange(selected);
    }
};

const handleSort = (column: string) => {
    if (!props.onSort) return;
    
    if (sortColumn.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'asc';
    }
    
    props.onSort(column, sortDirection.value);
};

const getInitials = (name: string) => {
    if (!name) return '';
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
};

const getAvatarColor = (name: string) => {
    const colors = [
        'bg-purple-500',
        'bg-blue-500',
        'bg-green-500',
        'bg-yellow-500',
        'bg-pink-500',
        'bg-indigo-500',
        'bg-red-500',
        'bg-teal-500',
    ];
    const index = name.charCodeAt(0) % colors.length;
    return colors[index];
};

const totalPages = computed(() => {
    const total = props.totalItems > 0 ? props.totalItems : tableData.value.length;
    const perPage = props.itemsPerPage || 5;
    const pages = Math.ceil(total / perPage);
    console.log('totalPages computed:', { total, perPage, pages, totalItems: props.totalItems, tableDataLength: tableData.value.length });
    return pages > 0 ? pages : 1;
});

const startItem = computed(() => {
    return (props.currentPage - 1) * props.itemsPerPage + 1;
});

const endItem = computed(() => {
    const end = props.currentPage * props.itemsPerPage;
    return Math.min(end, props.totalItems || tableData.value.length);
});

const getPageNumbers = computed(() => {
    const pages: (number | string)[] = [];
    const total = totalPages.value;
    const current = props.currentPage;
    
    if (total <= 7) {
        // Si moins de 7 pages, afficher toutes
        for (let i = 1; i <= total; i++) {
            pages.push(i);
        }
    } else {
        // Toujours afficher la première page
        pages.push(1);
        
        if (current <= 3) {
            // Près du début
            for (let i = 2; i <= 4; i++) {
                pages.push(i);
            }
            pages.push('...');
            pages.push(total);
        } else if (current >= total - 2) {
            // Près de la fin
            pages.push('...');
            for (let i = total - 3; i <= total; i++) {
                pages.push(i);
            }
        } else {
            // Au milieu
            pages.push('...');
            for (let i = current - 1; i <= current + 1; i++) {
                pages.push(i);
            }
            pages.push('...');
            pages.push(total);
        }
    }
    
    return pages;
});

const goToPage = (page: number) => {
    console.log('goToPage called:', { page, totalPages: totalPages.value, currentPage: props.currentPage, onPageChange: !!props.onPageChange });
    if (page >= 1 && page <= totalPages.value) {
        if (props.onPageChange) {
            console.log('Calling onPageChange with page:', page);
            props.onPageChange(page);
        } else {
            console.warn('onPageChange handler is not defined');
        }
    } else {
        console.warn('Page out of range:', { page, min: 1, max: totalPages.value });
    }
};

const handleItemsPerPageChange = (value: string) => {
    if (props.onItemsPerPageChange) {
        props.onItemsPerPageChange(parseInt(value));
    }
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th v-if="isSelectable" class="w-12 px-4 py-3">
                            <Checkbox
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                @update:checked="toggleSelectAll"
                                class="cursor-pointer"
                            />
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider"
                        >
                            <div class="flex items-center gap-2">
                                <span>{{ column.title || column.label }}</span>
                                <button
                                    v-if="column.sortable"
                                    @click="handleSort(column.key)"
                                    class="flex flex-col items-center justify-center hover:text-gray-900"
                                >
                                    <ChevronUp
                                        :class="[
                                            'h-4 w-4',
                                            sortColumn === column.key && sortDirection === 'asc'
                                                ? 'text-gray-900'
                                                : 'text-gray-400',
                                        ]"
                                    />
                                    <ChevronDown
                                        :class="[
                                            'h-4 w-4 -mt-1',
                                            sortColumn === column.key && sortDirection === 'desc'
                                                ? 'text-gray-900'
                                                : 'text-gray-400',
                                        ]"
                                    />
                                </button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <tr
                        v-for="(item, index) in tableData"
                        :key="index"
                        class="hover:bg-gray-50 transition-colors"
                    >
                        <td v-if="isSelectable" class="px-4 py-4">
                            <Checkbox
                                :checked="selectedRows.has(index)"
                                @update:checked="() => toggleSelectRow(index)"
                                class="cursor-pointer"
                            />
                        </td>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-4 text-base text-gray-900"
                        >
                            <!-- Support both #item.key (Vuetify style) and #cell-key (current style) -->
                            <slot
                                :name="`item.${column.key}`"
                                :item="item"
                                :value="item[column.key]"
                                :row="item"
                                :column="column"
                            >
                                <slot
                                    :name="`cell-${column.key}`"
                                    :value="item[column.key]"
                                    :row="item"
                                    :item="item"
                                    :column="column"
                                >
                                    <template v-if="column.render">
                                        <component :is="() => column.render!(item[column.key], item)" />
                                    </template>
                                    <template v-else>
                                        {{ item[column.key] }}
                                    </template>
                                </slot>
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="tableData.length === 0">
                        <td :colspan="columns.length + (isSelectable ? 1 : 0)" class="px-4 py-8 text-center text-base text-gray-500">
                            Aucune donnée disponible
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="(props.totalItems || tableData.length) > 0"
            class="flex items-center justify-between border-t border-gray-200 px-4 py-3 bg-gray-50"
        >
            <div class="flex items-center gap-2 text-base text-gray-700">
                <span>Items per page:</span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" class="h-8 w-20 justify-between">
                            {{ itemsPerPage }}
                            <ChevronDown class="h-5 w-5 opacity-50" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                        <DropdownMenuItem @click="handleItemsPerPageChange('5')">5</DropdownMenuItem>
                        <DropdownMenuItem @click="handleItemsPerPageChange('10')">10</DropdownMenuItem>
                        <DropdownMenuItem @click="handleItemsPerPageChange('25')">25</DropdownMenuItem>
                        <DropdownMenuItem @click="handleItemsPerPageChange('50')">50</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-base text-gray-700">
                    Entrée {{ startItem }} à {{ endItem }} sur {{ props.totalItems || tableData.length }} Entrées
                    (Page {{ props.currentPage }} / {{ totalPages }})
                </span>
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8"
                        :disabled="props.currentPage === 1 || totalPages <= 1"
                        @click="() => { console.log('Previous clicked'); goToPage(props.currentPage - 1); }"
                    >
                        ← Précedent
                    </Button>
                    <template v-if="totalPages > 1">
                        <template v-for="(page, index) in getPageNumbers" :key="index">
                            <Button
                                v-if="typeof page === 'number'"
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8"
                                :class="{ 'bg-purple-600 text-white hover:bg-purple-700': page === props.currentPage }"
                                @click="() => { console.log('Page number clicked:', page); goToPage(page); }"
                            >
                                {{ page }}
                            </Button>
                            <span v-else class="px-2 text-gray-500">...</span>
                        </template>
                    </template>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8"
                        :disabled="props.currentPage === totalPages || totalPages <= 1"
                        @click="() => { console.log('Next clicked'); goToPage(props.currentPage + 1); }"
                    >
                        Suivant →
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

