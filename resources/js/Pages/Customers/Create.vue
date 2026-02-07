<script setup>
import { ref, reactive } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';

const formRef = ref();

const form = useForm({
    // Datos del Cliente
    name: '',
    business_name: '',
    rfc: '',
    payment_condition: '',
    payment_method: '',
    invoice_usage: '',
    currency: 'MXN',
    
    // Lista de Contactos (Iniciamos con uno vacío)
    contacts: [
        { name: '', email: '', phone: '', position: '', branches: '' }
    ]
});

// Reglas de validación principales
const rules = reactive({
    name: [{ required: true, message: 'El nombre comercial es obligatorio', trigger: 'blur' }],
    business_name: [{ required: true, message: 'La razón social es obligatoria', trigger: 'blur' }],
    rfc: [{ required: true, message: 'El RFC es obligatorio', trigger: 'blur' }],
    payment_condition: [{ required: true, message: 'Selecciona una condición', trigger: 'change' }],
    payment_method: [{ required: true, message: 'Selecciona un método', trigger: 'change' }],
    invoice_usage: [{ required: true, message: 'Selecciona un uso de factura', trigger: 'change' }],
    currency: [{ required: true, message: 'Selecciona la moneda', trigger: 'change' }],
});

// Reglas para los contactos (se aplican dinámicamente)
const contactRules = {
    name: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    email: [
        { required: true, message: 'Requerido', trigger: 'blur' },
        { type: 'email', message: 'Email inválido', trigger: 'blur' }
    ],
    phone: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    position: [{ required: true, message: 'Requerido', trigger: 'blur' }],
    branches: [{ required: true, message: 'Requerido', trigger: 'blur' }],
};

// Métodos para gestión de contactos
const addContact = () => {
    form.contacts.push({ name: '', email: '', phone: '', position: '', branches: '' });
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
            form.post(route('customers.store'), {
                onSuccess: () => {
                    ElMessage.success('Cliente registrado correctamente');
                }
            });
        } else {
            ElMessage.error('Por favor revisa los campos marcados en rojo.');
            return false;
        }
    });
};
</script>

<template>
    <AppLayout title="Registrar nuevo cliente">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
                    Registrar nuevo cliente
                </h2>
                <Link :href="route('customers.index')">
                    <el-button icon="Back" circle />
                </Link>
            </div>
        </template>

        <div class="max-w-5xl mx-auto py-6 sm:px-6 lg:px-8">
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
                            <!-- Nombre Comercial -->
                            <el-form-item label="Nombre comercial" prop="name" :error="form.errors.name">
                                <el-input v-model="form.name" placeholder="Ej. Constructora del Norte" />
                            </el-form-item>

                            <!-- Razón Social -->
                            <el-form-item label="Razón social" prop="business_name" :error="form.errors.business_name">
                                <el-input v-model="form.business_name" placeholder="Ej. Constructora del Norte S.A. de C.V." />
                            </el-form-item>

                            <!-- RFC -->
                            <el-form-item label="RFC" prop="rfc" :error="form.errors.rfc">
                                <el-input v-model="form.rfc" placeholder="XAXX010101000" />
                            </el-form-item>

                            <!-- Moneda -->
                            <el-form-item label="Moneda" prop="currency" :error="form.errors.currency">
                                <el-select v-model="form.currency" class="w-full">
                                    <el-option label="Peso Mexicano (MXN)" value="MXN" />
                                    <el-option label="Dólar Americano (USD)" value="USD" />
                                </el-select>
                            </el-form-item>

                            <!-- Condiciones de Pago -->
                            <el-form-item label="Condiciones de pago" prop="payment_condition" :error="form.errors.payment_condition">
                                <el-select v-model="form.payment_condition" class="w-full" placeholder="Seleccionar">
                                    <el-option label="Facturación a crédito" value="Facturación a crédito" />
                                    <el-option label="Facturación al contado" value="Facturación al contado" />
                                    <el-option label="Otro" value="Otro" />
                                </el-select>
                            </el-form-item>

                            <!-- Método de Pago -->
                            <el-form-item label="Método de pago" prop="payment_method" :error="form.errors.payment_method">
                                <el-select v-model="form.payment_method" class="w-full" placeholder="Seleccionar">
                                    <el-option label="Transferencia electrónica" value="Transferencia" />
                                    <el-option label="Efectivo" value="Efectivo" />
                                    <el-option label="Otro" value="Otro" />
                                </el-select>
                            </el-form-item>

                            <!-- Uso de Factura -->
                            <el-form-item label="Uso de factura" prop="invoice_usage" :error="form.errors.invoice_usage" class="md:col-span-2 lg:col-span-3">
                                <el-radio-group v-model="form.invoice_usage">
                                    <el-radio label="Gastos en general" border>Gastos en general (G03)</el-radio>
                                    <el-radio label="Adquisición de mercancías" border>Adquisición de mercancías (G01)</el-radio>
                                    <el-radio label="Otro" border>Otro</el-radio>
                                </el-radio-group>
                            </el-form-item>
                        </div>
                    </div>

                    <!-- TARJETA 2: CONTACTOS -->
                    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
                        <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529] flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <el-icon class="text-primary"><User /></el-icon> 
                                Contactos asociados
                            </h3>
                            <el-button type="primary" plain size="small" icon="Plus" @click="addContact">
                                Agregar otro contacto
                            </el-button>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <div 
                                v-for="(contact, index) in form.contacts" 
                                :key="index"
                                class="relative p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:shadow-sm transition-shadow bg-gray-50/30 dark:bg-[#252529]/30"
                            >
                                <div class="absolute -top-3 left-4 bg-white dark:bg-[#1e1e20] px-2 text-xs font-bold text-gray-400">
                                    Contacto #{{ index + 1 }}
                                </div>

                                <div class="absolute top-2 right-2" v-if="form.contacts.length > 1">
                                    <el-button type="danger" link icon="Delete" @click="removeContact(index)" />
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mt-2">
                                    
                                    <!-- Nombre -->
                                    <el-form-item 
                                        label="Nombre completo" 
                                        :prop="'contacts.' + index + '.name'" 
                                        :rules="contactRules.name"
                                        class="lg:col-span-2"
                                    >
                                        <el-input v-model="contact.name" placeholder="Ej. María González" />
                                    </el-form-item>

                                    <!-- Email -->
                                    <el-form-item 
                                        label="Correo electrónico" 
                                        :prop="'contacts.' + index + '.email'" 
                                        :rules="contactRules.email"
                                        class="lg:col-span-1"
                                    >
                                        <el-input v-model="contact.email" placeholder="maria@email.com" />
                                    </el-form-item>

                                    <!-- Teléfono -->
                                    <el-form-item 
                                        label="Teléfono" 
                                        :prop="'contacts.' + index + '.phone'" 
                                        :rules="contactRules.phone"
                                        class="lg:col-span-1"
                                    >
                                        <el-input v-model="contact.phone" placeholder="55 1234 5678" />
                                    </el-form-item>
                                    
                                    <!-- Puesto -->
                                    <el-form-item 
                                        label="Puesto" 
                                        :prop="'contacts.' + index + '.position'" 
                                        :rules="contactRules.position"
                                        class="lg:col-span-1"
                                    >
                                        <el-input v-model="contact.position" placeholder="Ej. Compras" />
                                    </el-form-item>

                                    <!-- Sucursales -->
                                    <el-form-item 
                                        label="Sucursal(es)" 
                                        :prop="'contacts.' + index + '.branches'" 
                                        :rules="contactRules.branches"
                                        class="md:col-span-2 lg:col-span-5"
                                    >
                                        <el-input v-model="contact.branches" placeholder="Ej. Matriz, Sucursal Norte (Separar por comas si son varias)" />
                                    </el-form-item>
                                </div>
                            </div>
                            
                            <div v-if="form.contacts.length === 0" class="text-center py-4 text-gray-400">
                                No hay contactos. Pulsa "Agregar" para añadir uno.
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
                            Guardar cliente
                        </el-button>
                    </div>

                </div>
            </el-form>
        </div>
    </AppLayout>
</template> 