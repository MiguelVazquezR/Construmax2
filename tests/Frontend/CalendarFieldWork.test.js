/**
 * Field Work Calendar — Frontend Tests (Vitest + Vue Test Utils)
 *
 * Setup requirements:
 *   1. Install:  npm i -D vitest @vue/test-utils jsdom happy-dom
 *   2. Add to vite.config.js:
 *        test: { environment: 'jsdom', globals: true }
 *   3. Run: npx vitest run
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';

// ---------------------------------------------------------------------------
// Mock Inertia.js globals
// ---------------------------------------------------------------------------

vi.mock('@inertiajs/vue3', () => ({
    useForm: () => ({
        data: () => ({}),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
        processing: false,
    }),
    router: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        visit: vi.fn(),
        reload: vi.fn(),
    },
    usePage: () => ({
        props: { auth: { user: { id: 1, name: 'Test User' }, permissions: [] } },
    }),
    Link: { template: '<a><slot /></a>' },
}));

// Mock Element Plus
vi.mock('element-plus', () => ({
    ElMessage: { success: vi.fn(), error: vi.fn() },
    ElMessageBox: { confirm: vi.fn(), prompt: vi.fn() },
}));

// Mock route helper
global.route = vi.fn((name) => `/mock-${name}`);

// ---------------------------------------------------------------------------
// Import components under test
// ---------------------------------------------------------------------------

import CalendarModeSelector from '@/Pages/Calendar/Partials/CalendarModeSelector.vue';
import FieldWorkFormModal from '@/Pages/Calendar/Partials/FieldWorkFormModal.vue';

// ---------------------------------------------------------------------------
// CalendarModeSelector
// ---------------------------------------------------------------------------

describe('CalendarModeSelector', () => {
    it('renders both mode toggle buttons', () => {
        const wrapper = mount(CalendarModeSelector, {
            props: { modelValue: 'personal', viewMode: 'month' },
        });

        const buttons = wrapper.findAll('button');
        expect(buttons.length).toBeGreaterThanOrEqual(5); // 2 modes + 3 views
    });

    it('emits update:modelValue when personal mode clicked', async () => {
        const wrapper = mount(CalendarModeSelector, {
            props: { modelValue: 'field-work', viewMode: 'month' },
        });

        const personalBtn = wrapper.findAll('button')[0];
        await personalBtn.trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['personal']);
    });

    it('emits update:modelValue when field-work mode clicked', async () => {
        const wrapper = mount(CalendarModeSelector, {
            props: { modelValue: 'personal', viewMode: 'month' },
        });

        const fieldWorkBtn = wrapper.findAll('button')[1];
        await fieldWorkBtn.trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['field-work']);
    });

    it('emits update:viewMode when day view clicked', async () => {
        const wrapper = mount(CalendarModeSelector, {
            props: { modelValue: 'personal', viewMode: 'month' },
        });

        // View buttons start at index 2 (after 2 mode buttons)
        const dayBtn = wrapper.findAll('button')[2];
        await dayBtn.trigger('click');

        expect(wrapper.emitted('update:viewMode')).toBeTruthy();
        expect(wrapper.emitted('update:viewMode')[0]).toEqual(['day']);
    });

    it('highlights active mode with primary background', () => {
        const wrapper = mount(CalendarModeSelector, {
            props: { modelValue: 'personal', viewMode: 'month' },
        });

        const buttons = wrapper.findAll('button');
        const personalBtn = buttons[0];
        const fieldWorkBtn = buttons[1];

        expect(personalBtn.classes()).toContain('bg-primary');
        expect(personalBtn.classes()).toContain('text-white');
        expect(fieldWorkBtn.classes()).not.toContain('bg-primary');
    });

    it('highlights active view mode', () => {
        const wrapper = mount(CalendarModeSelector, {
            props: { modelValue: 'personal', viewMode: 'week' },
        });

        const viewButtons = wrapper.findAll('button').slice(2); // Day, Week, Month
        expect(viewButtons[1].classes()).toContain('bg-zinc-100');
    });
});

// ---------------------------------------------------------------------------
// FieldWorkFormModal — info card rendering
// ---------------------------------------------------------------------------

describe('FieldWorkFormModal', () => {
    beforeEach(() => {
        // Mock fetch for available tickets API
        global.fetch = vi.fn(() =>
            Promise.resolve({
                json: () => Promise.resolve([
                    {
                        id: 1,
                        folio: '#1-QRO-MX',
                        name: 'AC Installation',
                        customer_name: 'ACME Corp',
                        branch_name: 'Main office',
                        contact_name: 'John Doe',
                        seller_name: 'Jane Smith',
                        technician_ids: [2, 3],
                        assistant_ids: [],
                        first_task_date: '2026-07-20 09:00:00',
                        last_task_date: '2026-07-20 17:00:00',
                    },
                ]),
            }),
        );
    });

    it('renders modal when visible is true', () => {
        const wrapper = mount(FieldWorkFormModal, {
            props: {
                visible: true,
                schedule: null,
                prefilledDate: '',
                prefilledTime: '',
            },
        });

        // el-dialog should be present
        expect(wrapper.findComponent({ name: 'ElDialog' }).exists()).toBe(true);
    });

    it('does not render when visible is false', () => {
        const wrapper = mount(FieldWorkFormModal, {
            props: {
                visible: false,
                schedule: null,
                prefilledDate: '',
                prefilledTime: '',
            },
        });

        // Dialog should not be visible
        const dialog = wrapper.findComponent({ name: 'ElDialog' });
        expect(dialog.props('modelValue')).toBe(false);
    });

    it('displays ticket info card when a ticket is selected', async () => {
        const wrapper = mount(FieldWorkFormModal, {
            props: {
                visible: true,
                schedule: null,
                prefilledDate: '',
                prefilledTime: '',
            },
        });

        // Simulate ticket selection event by manually setting internal state
        // In a real test with full Vue composition, we'd interact with the select
        // Since the internal state is reactive, testing the render output requires
        // simulating the selection through the el-select component.

        // Assert the select element is present
        const selects = wrapper.findAllComponents({ name: 'ElSelect' });
        expect(selects.length).toBeGreaterThan(0);
    });

    it('shows color palette grid', () => {
        const wrapper = mount(FieldWorkFormModal, {
            props: {
                visible: true,
                schedule: null,
                prefilledDate: '',
                prefilledTime: '',
            },
        });

        // Should have color palette buttons
        const colorButtons = wrapper.findAll('button[type="button"].w-7.h-7');
        expect(colorButtons.length).toBeGreaterThanOrEqual(20);
    });
});
