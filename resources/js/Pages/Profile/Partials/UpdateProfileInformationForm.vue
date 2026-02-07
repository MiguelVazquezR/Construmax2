<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            clearPhotoFileInput();
            ElMessage.success('Información de perfil actualizada.');
        },
        onError: () => {
            ElMessage.error('Por favor revisa los errores.');
        }
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];
    if (! photo) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };
    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
            ElMessage.success('Foto de perfil eliminada.');
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <div class="bg-white dark:bg-[#1e1e20] shadow-sm rounded-lg border border-gray-100 dark:border-[#2b2b2e] overflow-hidden">
        
        <div class="p-6 border-b border-gray-100 dark:border-[#2b2b2e] bg-gray-50/50 dark:bg-[#252529]">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                <el-icon class="text-primary"><User /></el-icon> Información Personal
            </h3>
            <p class="text-sm text-gray-500 mt-1">Actualiza tu información de contacto y dirección de correo.</p>
        </div>

        <div class="p-6">
            <form @submit.prevent="updateProfileInformation">
                
                <!-- Profile Photo -->
                <div v-if="$page.props.jetstream.managesProfilePhotos" class="mb-6 flex flex-col items-center sm:items-start">
                    <input
                        id="photo"
                        ref="photoInput"
                        type="file"
                        class="hidden"
                        @change="updatePhotoPreview"
                    >

                    <div class="flex items-center gap-4">
                        <!-- Current Profile Photo -->
                        <div v-show="!photoPreview">
                            <el-avatar :size="80" :src="user.profile_photo_url" :alt="user.name" class="border-2 border-white shadow-sm" />
                        </div>

                        <!-- New Profile Photo Preview -->
                        <div v-show="photoPreview">
                            <el-avatar :size="80" :src="photoPreview" class="border-2 border-white shadow-sm" />
                        </div>

                        <div class="flex flex-col gap-2">
                            <el-button size="small" @click.prevent="selectNewPhoto">
                                Seleccionar nueva foto
                            </el-button>
                            <el-button 
                                v-if="user.profile_photo_path"
                                type="danger" 
                                link 
                                size="small"
                                @click.prevent="deletePhoto"
                            >
                                Eliminar foto
                            </el-button>
                        </div>
                    </div>
                    <p v-if="form.errors.photo" class="text-xs text-red-500 mt-2">{{ form.errors.photo }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre Completo</label>
                        <el-input v-model="form.name" size="large" />
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correo Electrónico</label>
                        <el-input v-model="form.email" size="large" />
                        <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>

                        <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null" class="mt-2">
                            <p class="text-sm text-yellow-600 dark:text-yellow-500">
                                Tu correo no ha sido verificado.
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="underline hover:text-gray-900 rounded-md focus:outline-none"
                                    @click.prevent="sendEmailVerification"
                                >
                                    Reenviar enlace de verificación.
                                </Link>
                            </p>
                            <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600">
                                Se ha enviado un nuevo enlace de verificación.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <el-button 
                        type="primary" 
                        native-type="submit" 
                        color="#f26c17" 
                        :loading="form.processing"
                        class="!font-bold"
                    >
                        Guardar Cambios
                    </el-button>
                </div>
            </form>
        </div>
    </div>
</template>