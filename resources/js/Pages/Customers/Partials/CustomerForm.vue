<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import { OfficeBuilding, User, Plus, Delete, LocationInformation } from '@element-plus/icons-vue';

const props = defineProps({
    customer: {
        type: Object,
        default: () => null,
    },
    isEdit: {
        type: Boolean,
        default: false,
    }
});

const formRef = ref();

// Inicializar Sucursales
const initBranches = () => {
    if (props.isEdit && props.customer?.branches?.length > 0) {
        return props.customer.branches.map(b => ({
            id: b.id,
            country: b.country || 'México',
            region: b.region || '',
            unit: b.unit || '',
            branch_name: b.branch_name || ''
        }));
    }
    return [{ country: 'México', region: '', unit: '', branch_name: '' }];
};

// Inicializar Contactos y mapear sus sucursales correspondientes
const initContacts = () => {
    if (props.isEdit && props.customer?.contacts?.length > 0) {
        return props.customer.contacts.map(c => {
            let branchIndices = [];
            
            // Si el contacto ya tiene sucursales asignadas en BD, buscamos su índice en el arreglo global
            if (c.branches && props.customer.branches) {
                c.branches.forEach(cb => {
                    const idx = props.customer.branches.findIndex(b => b.id === cb.id);
                    if (idx !== -1) branchIndices.push(idx);
                });
            }

            return {
                id: c.id,
                name: c.name,
                email: c.email,
                phone: c.phone,
                position: c.position,
                branch_indices: branchIndices
            };
        });
    }
    
    return [{ 
        name: '', 
        email: '', 
        phone: '', 
        position: '', 
        branch_indices: [] 
    }];
};

const form = useForm({
    name: props.customer?.name || '',
    business_name: props.customer?.business_name || '',
    rfc: props.customer?.rfc || '',
    payment_condition: props.customer?.payment_condition || '',
    payment_method: props.customer?.payment_method || '',
    invoice_usage: props.customer?.invoice_usage || '',
    currency: props.customer?.currency || 'MXN',
    payment_days: props.customer?.payment_days || 0,
    branches: initBranches(),
    contacts: initContacts()
});

// Reglas de validación generales
const rules = reactive({
    name: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    business_name: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    rfc: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    payment_condition: [{ required: true, message: 'Requerido', trigger: 'change' }],
    payment_method: [{ required: true, message: 'Requerido', trigger: 'change' }],
    invoice_usage: [{ required: true, message: 'Requerido', trigger: 'change' }],
    currency: [{ required: true, message: 'Requerido', trigger: 'change' }],
});

// Reglas dinámicas
const requiredRules = [{ required: true, message: 'Requerido', trigger: 'blur' }];
const emailRules = [
    { required: true, message: 'Requerido', trigger: 'blur' },
    { type: 'email', message: 'Email inválido', trigger: 'blur' }
];

// Métodos para Sucursales
const addBranch = () => {
    form.branches.push({ country: 'México', region: '', unit: '', branch_name: '' });
};

const removeBranch = (index) => {
    if (form.branches.length > 1) {
        form.branches.splice(index, 1);
        
        // Reajustar los índices seleccionados en los contactos
        form.contacts.forEach(c => {
            c.branch_indices = c.branch_indices
                .filter(i => i !== index) // Remover la eliminada
                .map(i => i > index ? i - 1 : i); // Recorrer los índices superiores
        });
    } else {
        ElMessage.warning('El cliente debe tener al menos una sucursal.');
    }
};

// Métodos para Contactos
const addContact = () => {
    form.contacts.push({ name: '', email: '', phone: '', position: '', branch_indices: [] });
};

const removeContact = (index) => {
    if (form.contacts.length > 1) {
        form.contacts.splice(index, 1);
    } else {
        ElMessage.warning('Debes registrar al menos un contacto.');
    }
};

const submit = () => {
    if (!formRef.value) return;
    
    formRef.value.validate((valid) => {
        if (valid) {
            // Validar que todos los contactos tengan al menos una sucursal
            const invalidContact = form.contacts.find(c => c.branch_indices.length === 0);
            if (invalidContact) {
                ElMessage.error('Todos los contactos deben tener al menos una sucursal a su cargo.');
                return false;
            }

            if (props.isEdit) {
                form.put(route('customers.update', props.customer.id), {
                    onSuccess: () => ElMessage.success('Cliente actualizado correctamente')
                });
            } else {
                form.post(route('customers.store'), {
                    onSuccess: () => ElMessage.success('Cliente registrado correctamente')
                });
            }
        } else {
            ElMessage.error('Por favor revisa los campos marcados en rojo.');
            return false;
        }
    });
};
</script>

<template>
    <el-form 
        ref="formRef"
        :model="form" 
        :rules="rules" 
        label-position="top"
        require-asterisk-position="right"
        size="large"
        @submit.prevent="submit"
    >
        <div class="space-y-6">
            
            <!-- TARJETA 1: DATOS GENERALES -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <el-icon class="text-primary"><OfficeBuilding /></el-icon> 
                        Información fiscal y comercial
                    </h3>
                </div>
                
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <el-form-item label="Nombre comercial" prop="name" :error="form.errors.name">
                        <el-input v-model="form.name" placeholder="Ej. Constructora del Norte" />
                    </el-form-item>

                    <el-form-item label="Razón social" prop="business_name" :error="form.errors.business_name">
                        <el-input v-model="form.business_name" placeholder="Ej. Constructora del Norte S.A. de C.V." />
                    </el-form-item>

                    <el-form-item label="RFC" prop="rfc" :error="form.errors.rfc">
                        <el-input v-model="form.rfc" placeholder="XAXX010101000" />
                    </el-form-item>

                    <el-form-item label="Moneda" prop="currency" :error="form.errors.currency">
                        <el-select v-model="form.currency" class="w-full">
                            <el-option label="Peso Mexicano (MXN)" value="MXN" />
                            <el-option label="Dólar Americano (USD)" value="USD" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Condiciones de pago" prop="payment_condition" :error="form.errors.payment_condition">
                        <el-select v-model="form.payment_condition" class="w-full" placeholder="Seleccionar">
                            <el-option label="Facturación a crédito" value="Facturación a crédito" />
                            <el-option label="Facturación al contado" value="Facturación al contado" />
                            <el-option label="Otro" value="Otro" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Días de crédito (Plazo)" prop="payment_days" :error="form.errors.payment_days">
                        <el-input-number 
                            v-model="form.payment_days" 
                            :min="0" 
                            :max="365"
                            class="!w-full"
                            controls-position="right"
                        >
                            <template #suffix>Días</template>
                        </el-input-number>
                    </el-form-item>

                    <el-form-item label="Método de pago" prop="payment_method" :error="form.errors.payment_method">
                        <el-select v-model="form.payment_method" class="w-full" placeholder="Seleccionar">
                            <el-option label="Transferencia electrónica" value="Transferencia" />
                            <el-option label="Efectivo" value="Efectivo" />
                            <el-option label="Otro" value="Otro" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Uso de factura" prop="invoice_usage" :error="form.errors.invoice_usage" class="md:col-span-2 lg:col-span-3">
                        <el-radio-group v-model="form.invoice_usage">
                            <el-radio label="Gastos en general" border>Gastos en general (G03)</el-radio>
                            <el-radio label="Adquisición de mercancías" border>Adquisición de mercancías (G01)</el-radio>
                            <el-radio label="Otro" border>Otro</el-radio>
                        </el-radio-group>
                    </el-form-item>
                </div>
            </div>

            <!-- TARJETA 2: SUCURSALES (Globales del cliente) -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529] flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <el-icon class="text-primary"><LocationInformation /></el-icon> 
                            Sucursales o Sitios
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Registra todas las sucursales, unidades operativas o sitios donde tiene presencia el cliente.
                        </p>
                    </div>
                    <el-button type="primary" plain icon="Plus" @click="addBranch">
                        Agregar sucursal
                    </el-button>
                </div>

                <div class="p-6 space-y-4">
                    <div 
                        v-for="(branch, bIndex) in form.branches" 
                        :key="bIndex"
                        class="relative flex flex-col gap-3 p-5 pt-8 bg-gray-50 dark:bg-[#252529]/50 border border-gray-200 dark:border-gray-700 rounded-lg"
                    >
                        <div class="absolute -top-3 left-4 bg-orange-100 dark:bg-orange-900/30 px-3 py-1 rounded-full text-xs font-bold text-orange-600 dark:text-orange-400 flex items-center gap-1">
                            <el-icon><LocationInformation /></el-icon> Sucursal #{{ bIndex + 1 }}
                        </div>

                        <div class="absolute top-3 right-3">
                            <el-button 
                                type="danger" 
                                plain
                                size="small"
                                icon="Delete" 
                                @click="removeBranch(bIndex)" 
                                v-if="form.branches.length > 1"
                                title="Remover sucursal"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                            <el-form-item 
                                label="País" 
                                :prop="'branches.' + bIndex + '.country'" 
                                :rules="requiredRules"
                                class="mb-0"
                            >
                                <el-input v-model="branch.country" placeholder="Ej. México" />
                            </el-form-item>

                            <el-form-item 
                                label="Región / Estado" 
                                :prop="'branches.' + bIndex + '.region'" 
                                :rules="requiredRules"
                                class="mb-0"
                            >
                                <el-input v-model="branch.region" placeholder="Ej. Jalisco" />
                            </el-form-item>

                            <el-form-item 
                                label="Unidad" 
                                :prop="'branches.' + bIndex + '.unit'" 
                                :rules="requiredRules"
                                class="mb-0"
                            >
                                <el-input v-model="branch.unit" placeholder="Ej. UN-01" />
                            </el-form-item>

                            <el-form-item 
                                label="Nombre de Sucursal" 
                                :prop="'branches.' + bIndex + '.branch_name'" 
                                :rules="requiredRules"
                                class="mb-0"
                            >
                                <el-input v-model="branch.branch_name" placeholder="Ej. Matriz Zapopan" />
                            </el-form-item>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TARJETA 3: CONTACTOS (Y asignación de sucursales) -->
            <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529] flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <el-icon class="text-primary"><User /></el-icon> 
                            Contactos / Encargados
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Añade los contactos y asígnales qué sucursales operan o administran.
                        </p>
                    </div>
                    <el-button type="primary" plain icon="Plus" @click="addContact">
                        Agregar contacto
                    </el-button>
                </div>
                
                <div class="p-6 space-y-6">
                    <div 
                        v-for="(contact, index) in form.contacts" 
                        :key="index"
                        class="relative p-5 pt-7 border-2 border-gray-100 dark:border-gray-800 rounded-xl bg-white dark:bg-[#1e1e20]"
                    >
                        <div class="absolute -top-3.5 left-4 bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-full text-xs font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1">
                            <el-icon><User /></el-icon> Contacto #{{ index + 1 }}
                        </div>

                        <div class="absolute top-3 right-3" v-if="form.contacts.length > 1">
                            <el-button type="danger" plain size="small" icon="Delete" @click="removeContact(index)">
                                Eliminar
                            </el-button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
                            <el-form-item label="Nombre completo" :prop="'contacts.' + index + '.name'" :rules="requiredRules">
                                <el-input v-model="contact.name" placeholder="Ej. María González" />
                            </el-form-item>
                            
                            <el-form-item label="Correo electrónico" :prop="'contacts.' + index + '.email'" :rules="emailRules">
                                <el-input v-model="contact.email" placeholder="maria@email.com" />
                            </el-form-item>
                            
                            <el-form-item label="Teléfono" :prop="'contacts.' + index + '.phone'" :rules="requiredRules">
                                <el-input v-model="contact.phone" placeholder="55 1234 5678" />
                            </el-form-item>
                            
                            <el-form-item label="Puesto" :prop="'contacts.' + index + '.position'" :rules="requiredRules">
                                <el-input v-model="contact.position" placeholder="Ej. Compras" />
                            </el-form-item>

                            <!-- ASIGNACIÓN DE SUCURSALES AL CONTACTO -->
                            <el-form-item 
                                label="Sucursales a cargo" 
                                :prop="'contacts.' + index + '.branch_indices'" 
                                :rules="[{ required: true, message: 'Asigna al menos una', trigger: 'change' }]"
                                class="lg:col-span-4"
                            >
                                <el-select 
                                    v-model="contact.branch_indices" 
                                    multiple 
                                    collapse-tags
                                    collapse-tags-tooltip
                                    placeholder="Selecciona las sucursales que administra este contacto..." 
                                    class="w-full"
                                >
                                    <el-option 
                                        v-for="(b, bIdx) in form.branches" 
                                        :key="bIdx" 
                                        :label="b.branch_name ? `${b.branch_name} (${b.unit}) - ${b.region}` : (b.unit ? `${b.unit} - ${b.region}` : `Sucursal #${bIdx + 1}`)" 
                                        :value="bIdx" 
                                    />
                                </el-select>
                            </el-form-item>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTONES ACCIÓN -->
            <div class="flex justify-end gap-3 pt-4">
                <Link :href="route('customers.index')">
                    <el-button size="large">Cancelar</el-button>
                </Link>
                <el-button 
                    type="primary" 
                    native-type="submit" 
                    size="large" 
                    :loading="form.processing" 
                    color="#f26c17"
                    class="!font-bold"
                >
                    {{ isEdit ? 'Actualizar cliente' : 'Guardar cliente' }}
                </el-button>
            </div>

        </div>
    </el-form>
</template>