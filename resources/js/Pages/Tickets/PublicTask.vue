<template>
  <Head :title="`Orden de Trabajo #${ticket.id}`" />
  
  <div class="min-h-screen bg-gray-50/50 dark:bg-gray-900">
    <div class="max-w-3xl mx-auto p-4 sm:p-6 lg:p-8">
      
      <!-- 1. AVISO DE SEGURIDAD (IMPORTANTE) -->
      <div class="sticky top-4 z-50 mb-8">
        <div class="bg-orange-50 border border-orange-200 rounded-xl shadow-sm p-4 backdrop-blur-sm bg-opacity-95 dark:bg-orange-900/30 dark:border-orange-700">
          <div class="flex items-start gap-3">
            <el-icon class="text-orange-500 mt-0.5" :size="20"><Warning /></el-icon>
            <div>
              <h3 class="text-sm font-bold text-orange-800 mb-2 uppercase tracking-wide dark:text-orange-200">Aviso de Seguridad Industrial</h3>
              <p class="text-xs font-semibold text-orange-700 mb-2 dark:text-orange-300">Reglas obligatorias para iniciar labores:</p>
              <ul class="text-xs text-orange-700 list-disc pl-4 space-y-1 dark:text-orange-300">
                  <li>Usar equipo de protección personal (EPP) completo.</li>
                  <li>Verificar riesgos eléctricos o de altura.</li>
                  <li>Reportar condiciones inseguras antes de iniciar.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- HEADER DEL TICKET -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-10 relative overflow-hidden dark:bg-gray-800 dark:border-gray-700">
        <!-- Decoración sutil de fondo -->
        <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-gray-50 rounded-full opacity-50 pointer-events-none dark:bg-gray-700"></div>
        
        <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-6 relative z-10">
            <div>
                <div class="flex items-center gap-3 mb-2">
                  <span class="text-xs font-bold text-gray-400 uppercase tracking-widest dark:text-gray-300">Ticket {{ ticket.folio }}</span>
                  <el-tag effect="light" :type="getPriorityColor(ticket.priority)" size="small" class="!rounded-full !border-none !px-3 font-semibold">
                    Prioridad {{ ticket.priority }}
                  </el-tag>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight leading-tight dark:text-gray-100">{{ ticket.budget?.customer?.name }}</h1>
                <p class="text-sm text-gray-500 mt-1 font-medium dark:text-gray-400">{{ ticket.budget?.customer?.business_name }}</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t border-gray-100 relative z-10 dark:border-gray-700">
          <div>
            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 dark:text-gray-300">Técnico asignado</span>
            <div class="flex items-center gap-3">
                <el-avatar :size="32" :src="technician.profile_photo_url" class="border border-gray-100 shadow-sm dark:border-gray-600">{{ technician.name.charAt(0) }}</el-avatar>
                <div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {{ technician.name }}
                        <template v-if="technician.technician?.state"> — {{ technician.technician.state }}</template>
                    </span>
                    <el-tag
                        v-if="technician.technician"
                        size="small"
                        :type="technician.technician.is_internal ? 'success' : 'warning'"
                        effect="dark"
                        class="ml-2 !h-4 !text-[9px] !px-1"
                    >
                        {{ technician.technician.is_internal ? 'Interno' : 'Externo' }}
                    </el-tag>
                </div>
            </div>
          </div>
          <div>
            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 dark:text-gray-300">Cliente</span>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ ticket.customer?.name || ticket.budget?.customer?.name || '---' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ ticket.branch?.branch_name || 'Sucursal no especificada' }}</p>
          </div>
          <div class="md:col-span-2">
            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 dark:text-gray-300">Ubicación</span>
            <p class="text-sm text-gray-600 dark:text-gray-300">
              {{ [ticket.branch?.branch_name, ticket.branch?.unit, ticket.branch?.city, ticket.branch?.region].filter(Boolean).join(', ') || 'No disponible' }}
              <span v-if="ticket.contact?.name" class="text-gray-400"> — Contacto: {{ ticket.contact?.name }} {{ ticket.contact?.phone ? `(${ticket.contact.phone})` : '' }}</span>
            </p>
          </div>
          <div class="md:col-span-2">
            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 dark:text-gray-300">Instrucciones Generales</span>
            <p class="text-sm text-gray-600 leading-relaxed bg-gray-50 p-3 rounded-lg border border-gray-100 dark:text-gray-300 dark:bg-gray-700/50 dark:border-gray-600">
              {{ ticket.instructions || 'No se proporcionaron instrucciones específicas para esta orden.' }}
            </p>
          </div>
        </div>
      </div>

      <!-- INFORMACIÓN DE PAGOS AL TÉCNICO -->
      <div v-if="ticket.budget?.technician_payments?.length > 0 || totalTechnicianAmount > 0" class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2 dark:text-gray-100">
          <el-icon class="text-green-500"><Money /></el-icon> Información de pagos
        </h2>
        
        <div v-if="totalTechnicianAmount > 0" class="bg-white rounded-xl border border-gray-100 p-4 mb-4 shadow-sm dark:bg-gray-800 dark:border-gray-700">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Progreso de pagos</span>
            <span class="text-sm font-bold dark:text-gray-100">{{ formatPaymentCurrency(totalTechnicianPaid) }} / {{ formatPaymentCurrency(totalTechnicianAmount) }}</span>
          </div>
          <el-progress
            :percentage="technicianPaymentProgress"
            :status="technicianPaymentProgress >= 100 ? 'success' : ''"
            :stroke-width="14"
          >
            <span class="text-xs font-bold dark:text-gray-100">{{ technicianPaymentProgress }}%</span>
          </el-progress>
        </div>

        <div v-if="ticket.budget?.technician_payments?.length > 0" class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700">
          <h3 class="text-sm font-bold text-gray-700 mb-3 dark:text-gray-200">Historial de pagos</h3>
          <div class="space-y-2">
            <div v-for="pay in ticket.budget.technician_payments" :key="pay.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-100 text-sm dark:bg-gray-700/50 dark:border-gray-600">
              <div class="flex items-center gap-2">
                <span class="font-bold text-green-600 dark:text-green-400">{{ formatPaymentCurrency(pay.amount) }}</span>
                <span class="text-xs text-gray-400 dark:text-gray-300">{{ dayjs(pay.payment_date).format('DD MMM YYYY') }}</span>
                <el-tag v-if="pay.reference" size="small" type="info" class="scale-75">{{ pay.reference }}</el-tag>
              </div>
              <div>
                <el-tooltip v-if="pay.media?.length" content="Ver comprobante">
                  <el-button circle size="small" icon="Document" @click="showImage(pay.media[0].original_url)" />
                </el-tooltip>
              </div>
            </div>
          </div>
        </div>
      </div>

       <!-- RECURSOS DEL TICKET (Imágenes de apoyo subidas desde la oficina) -->
      <div v-if="ticket.media && ticket.media.length > 0" class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2 dark:text-gray-100">
          <el-icon class="text-primary"><IconPicture /></el-icon> Recursos e imágenes de apoyo
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
          <div v-for="file in ticket.media" :key="file.id" class="border border-gray-200 rounded-lg overflow-hidden bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="aspect-video bg-gray-100 flex items-center justify-center dark:bg-gray-700">
              <img v-if="file.mime_type?.startsWith('image/')" :src="file.original_url" class="w-full h-full object-cover cursor-pointer" @click="showImage(file.original_url)" />
              <el-icon v-else :size="32" class="text-gray-400 dark:text-gray-300"><Document /></el-icon>
            </div>
            <p class="text-xs p-2 truncate text-gray-500 dark:text-gray-400">{{ file.file_name }}</p>
          </div>
        </div>
      </div>


      <!-- LISTA DE TAREAS SECUENCIALES -->
      <div class="mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2 dark:text-gray-100">
          <el-icon class="text-primary"><List /></el-icon> Plan de Trabajo
        </h2>
        
        <div class="space-y-6 relative">
            <!-- Línea conectora vertical sutil -->
            <div class="absolute left-[1.15rem] top-4 bottom-4 w-px bg-gray-200 z-0 hidden sm:block dark:bg-gray-700"></div>

            <div 
                v-for="(task, index) in tasks" 
                :key="task.id" 
                class="relative z-10 sm:pl-12 transition-all duration-300 group"
                :class="{ 'opacity-50 grayscale': isTaskLocked(index) }"
            >
                <!-- Badge Circular del Paso -->
                <div 
                    class="step-badge hidden sm:flex absolute left-0 top-6 w-10 h-10 rounded-full items-center justify-center font-bold text-sm shadow-sm border-4 border-gray-50 transition-colors dark:border-gray-800"
                    :class="getStepColorClass(task, index)"
                >
                    <el-icon v-if="task.status === 'Completada'" :size="16"><Check /></el-icon>
                    <span v-else>{{ index + 1 }}</span>
                </div>

                <!-- Tarjeta de Tarea -->
                <el-card class="!rounded-2xl !border-gray-100 !shadow-sm hover:!shadow-md transition-shadow dark:!border-gray-700" :shadow="isTaskLocked(index) ? 'never' : 'hover'">
                    <template #header>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b-0 pb-0">
                            <div class="flex items-center gap-3">
                              <span class="step-badge sm:hidden flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white" :class="getStepColorClass(task, index).replace('border-4 border-gray-50', '')">
                                <el-icon v-if="task.status === 'Completada'" :size="12"><Check /></el-icon>
                                <span v-else>{{ index + 1 }}</span>
                              </span>
                              <h3 class="text-base font-bold text-gray-800 leading-tight m-0 dark:text-gray-100">{{ task.name }}</h3>
                            </div>
                            <el-tag size="small" effect="light" :type="getStatusType(task.status)" class="!rounded-full !px-3 font-medium !border-none bg-opacity-20">{{ task.status }}</el-tag>
                        </div>
                    </template>

                    <div class="text-sm text-gray-600 mb-6 leading-relaxed whitespace-pre-line dark:text-gray-300">
                        {{ task.description || 'Sin detalles adicionales para esta tarea.' }}
                    </div>
                    
                    <div class="flex flex-wrap gap-4 mb-6 pb-6 border-b border-gray-50 dark:border-gray-700">
                        <div class="flex items-center gap-2 text-xs font-medium text-gray-500 bg-gray-50/80 px-3 py-1.5 rounded-md dark:text-gray-400 dark:bg-gray-700/50">
                          <el-icon class="text-gray-400 dark:text-gray-300"><Calendar /></el-icon> 
                          <span>Inicio: {{ formatDate(task.start_date) }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs font-medium text-gray-500 bg-gray-50/80 px-3 py-1.5 rounded-md dark:text-gray-400 dark:bg-gray-700/50">
                          <el-icon class="text-gray-400 dark:text-gray-300"><Timer /></el-icon> 
                          <span>Límite: {{ formatDate(task.due_date) }}</span>
                        </div>
                    </div>

                    <!-- SECCIÓN DE EVIDENCIAS -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-4">
                          <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2 dark:text-gray-300">
                              <el-icon><Camera /></el-icon> Evidencias Fotográficas
                          </h4>
                          <div v-if="task.status !== 'Completada' && !isTaskLocked(index)" class="flex items-center gap-2">
                              <el-upload
                                  action="#"
                                  :auto-upload="false"
                                  :show-file-list="false"
                                  :on-change="(f) => handleFileSelect(f, task)"
                                  accept="image/*,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska"
                                  multiple
                              >
                                  <el-button size="small" :icon="Camera" plain type="primary" class="!rounded-full">
                                      Seleccionar archivos
                                  </el-button>
                              </el-upload>
                              <el-button
                                  v-if="pendingEvidence[task.id]?.length > 0"
                                  size="small"
                                  type="success"
                                  :loading="uploadingTaskIds[task.id]"
                                  @click="flushUpload(task)"
                              >
                                  Subir ({{ pendingEvidence[task.id].length }})
                              </el-button>
                          </div>
                        </div>
                        
                        <!-- Galería -->
                        <div v-if="task.media && task.media.length > 0" class="flex flex-wrap gap-3">
                            <div 
                                v-for="(img, imgIndex) in task.media" 
                                :key="img.id" 
                                class="relative w-24 h-24 rounded-xl overflow-hidden border border-gray-100 shadow-sm group/img dark:border-gray-600"
                            >
                                <!-- Image -->
                                <el-image 
                                    v-if="img.mime_type?.startsWith('image/')"
                                    :src="img.original_url" 
                                    class="w-full h-full transition-transform duration-300 group-hover/img:scale-110" 
                                    fit="cover"
                                    :preview-src-list="getTaskImageUrls(task)"
                                    :initial-index="imgIndex"
                                    preview-teleported
                                    hide-on-click-modal
                                >
                                    <template #error>
                                        <div class="flex items-center justify-center w-full h-full bg-gray-50 text-gray-300 dark:bg-gray-700 dark:text-gray-500">
                                            <el-icon :size="24"><icon-picture /></el-icon>
                                        </div>
                                    </template>
                                </el-image>
                                <!-- Video -->
                                <div v-else-if="img.mime_type?.startsWith('video/')" class="w-full h-full bg-gray-900 flex items-center justify-center cursor-pointer" @click="showImage(img.original_url)">
                                    <video class="w-full h-full object-cover" muted preload="metadata">
                                        <source :src="img.original_url" />
                                    </video>
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                        <el-icon class="text-white" :size="28"><VideoCamera /></el-icon>
                                    </div>
                                </div>

                                <!-- Botón borrar -->
                                <el-popconfirm
                                    v-if="task.status !== 'Completada'"
                                    :title="img.mime_type?.startsWith('video/') ? '¿Eliminar video?' : '¿Eliminar foto?'"
                                    confirm-button-text="Sí"
                                    cancel-button-text="No"
                                    width="180"
                                    @confirm="deleteEvidence(img.delete_url)"
                                >
                                    <template #reference>
                                        <div class="absolute top-1.5 right-1.5 bg-white/90 backdrop-blur-sm text-red-500 rounded-full p-1.5 shadow-sm cursor-pointer hover:bg-red-50 hover:text-red-600 transition-colors z-10 flex items-center justify-center opacity-0 group-hover/img:opacity-100 sm:opacity-100 dark:bg-gray-800/90 dark:hover:bg-red-900/30">
                                            <el-icon :size="14"><Delete /></el-icon>
                                        </div>
                                    </template>
                                </el-popconfirm>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-6 px-4 text-center bg-gray-50 border border-dashed border-gray-200 rounded-xl dark:bg-gray-700/30 dark:border-gray-600">
                            <el-icon class="text-gray-300 mb-2 dark:text-gray-500" :size="24"><PictureFilled /></el-icon>
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Sin evidencias registradas</span>
                            <span class="text-xs text-gray-400 mt-1 dark:text-gray-500">Sube fotos del antes, durante y después.</span>
                        </div>
                    </div>

                    <!-- SECCIÓN DE COMENTARIOS DEL TÉCNICO -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider flex items-center gap-2 dark:text-gray-300">
                                <el-icon><ChatDotSquare /></el-icon> Notas y comentarios del técnico
                            </h4>
                        </div>
                        <div class="bg-gray-50 rounded-xl border border-gray-100 p-3 dark:bg-gray-700/50 dark:border-gray-600">
                            <el-input
                                v-model="task.technician_notes"
                                type="textarea"
                                :rows="3"
                                placeholder="Escribe aquí tus notas, observaciones o comentarios sobre esta tarea..."
                                @blur="saveNotes(task)"
                            />
                            <div class="flex justify-end mt-2">
                                <el-button size="small" type="primary" plain @click="saveNotes(task)" :loading="savingNotesId === task.id">
                                    Guardar notas
                                </el-button>
                            </div>
                        </div>
                    </div>

                    <!-- ACCIÓN PRINCIPAL -->
                    <el-button 
                        v-if="!isTaskLocked(index)"
                        :type="task.status === 'Completada' ? 'info' : 'success'" 
                        class="w-full !py-6 !text-base !font-bold !rounded-xl transition-all"
                        :plain="task.status === 'Completada'"
                        :icon="task.status === 'Completada' ? RefreshLeft : Select"
                        @click="toggleStatus(task)"
                        :loading="togglingTaskId === task.id"
                    >
                        {{ task.status === 'Completada' ? 'Reabrir tarea para edición' : 'Marcar tarea como completada' }}
                    </el-button>
                    
                    <div v-else class="flex items-center justify-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-sm font-medium text-gray-400 dark:bg-gray-700/50 dark:border-gray-600 dark:text-gray-500">
                        <el-icon><Lock /></el-icon> Completa el paso anterior para desbloquear
                    </div>

                </el-card>
            </div>
        </div>
      </div>

      <!-- ACTA DE RECEPCIÓN -->
      <div class="mb-8">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm dark:bg-gray-800 dark:border-gray-700">
          <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <el-icon class="text-green-600 dark:text-green-400" :size="20"><DocumentChecked /></el-icon>
            </div>
            <div>
              <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">Acta de recepción</h2>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Los datos que registres aquí se reflejarán en el acta de recepción que firmará el gerente de sucursal.
              </p>
            </div>
            <el-tag v-if="isReportSigned" type="success" size="small" effect="light" class="!rounded-full ml-auto">
              Firmado
            </el-tag>
          </div>

          <!-- Editable fields (only if report exists and not signed) -->
          <template v-if="workAcceptanceReportData && !isReportSigned">
            <div class="space-y-4">
              <!-- Work description -->
              <div>
                <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1">
                  Descripción de trabajos realizados
                </label>
                <el-input
                  v-model="reportForm.work_description"
                  type="textarea"
                  :rows="4"
                  placeholder="Describe detalladamente los trabajos realizados en sitio..."
                  maxlength="5000"
                  show-word-limit
                />
              </div>

              <!-- On-site dates -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1">
                    Fecha y hora de inicio en sitio
                  </label>
                  <el-date-picker
                    v-model="reportForm.on_site_start"
                    type="datetime"
                    placeholder="Inicio en sitio"
                    format="DD/MM/YYYY hh:mm a"
                    class="!w-full"
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1">
                    Fecha y hora de finalización en sitio
                  </label>
                  <el-date-picker
                    v-model="reportForm.on_site_end"
                    type="datetime"
                    placeholder="Fin en sitio"
                    format="DD/MM/YYYY hh:mm a"
                    class="!w-full"
                  />
                </div>
              </div>

              <!-- Technician comments -->
              <div>
                <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1">
                  Comentarios del técnico
                </label>
                <el-input
                  v-model="reportForm.technician_comments"
                  type="textarea"
                  :rows="3"
                  placeholder="Observaciones, notas o comentarios adicionales..."
                  maxlength="2000"
                  show-word-limit
                />
              </div>

              <!-- Save button -->
              <div class="flex justify-end">
                <el-button
                  type="primary"
                  :loading="savingReport"
                  @click="saveReport"
                  :icon="Select"
                >
                  Guardar datos del acta
                </el-button>
              </div>
            </div>
          </template>

          <!-- Read-only when signed -->
          <div v-else-if="isReportSigned" class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Este documento ya fue firmado por el gerente de sucursal. No se pueden realizar más modificaciones.
            </p>
          </div>

          <!-- No report generated yet -->
          <div v-else class="text-center py-4 text-sm text-gray-400 dark:text-gray-500">
            El acta de recepción aún no ha sido generada desde la oficina.
          </div>

          <!-- View / Sign buttons -->
          <div v-if="workAcceptanceReportUrls" class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex flex-wrap gap-2">
            <a
              :href="workAcceptanceReportUrls.public_show"
              target="_blank"
              class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#f26c17] hover:bg-[#d95d0f] text-white text-sm font-bold rounded-lg transition-colors no-underline"
            >
              <el-icon :size="16"><View /></el-icon>
              {{ workAcceptanceReportUrls.is_signed ? 'Ver acta firmada' : 'Ver acta de recepción' }}
            </a>
          </div>
        </div>
      </div>

      <!-- 3. RECORDATORIO FINAL (CHECKLIST DE CIERRE) -->
      <div class="bg-blue-50/50 border border-blue-100 rounded-2xl p-6 sm:p-8 mt-12 relative overflow-hidden dark:bg-blue-900/20 dark:border-blue-800">
          <div class="absolute -right-6 -top-6 text-blue-100 opacity-50 dark:text-blue-800">
            <el-icon :size="120"><DocumentChecked /></el-icon>
          </div>
          
          <div class="relative z-10">
            <h3 class="text-blue-900 text-base font-bold flex items-center gap-2 mb-4 dark:text-blue-100">
                <el-icon class="text-blue-500 dark:text-blue-400"><List /></el-icon> Requisitos para liberación de pago
            </h3>
            <div class="bg-white rounded-xl p-5 border border-blue-100 shadow-sm mb-4 dark:bg-gray-800 dark:border-blue-800">
              <ul class="text-sm text-blue-800 space-y-4 list-none dark:text-blue-200">
                  <li class="flex items-start gap-3">
                      <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 dark:bg-blue-800 dark:text-blue-400">
                        <el-icon :size="12"><Check /></el-icon>
                      </div>
                      <span class="font-medium leading-relaxed">Subir evidencias fotográficas claras del <strong class="text-blue-900 dark:text-blue-100">Antes, Durante y Después</strong>.</span>
                  </li>
                  <li class="flex items-start gap-3">
                      <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 dark:bg-blue-800 dark:text-blue-400">
                        <el-icon :size="12"><Check /></el-icon>
                      </div>
                      <span class="font-medium leading-relaxed">Firma de conformidad del cliente en la <strong class="text-blue-900 dark:text-blue-100">Hoja de servicio</strong>.</span>
                  </li>
                  <li class="flex items-start gap-3">
                      <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 dark:bg-blue-800 dark:text-blue-400">
                        <el-icon :size="12"><Check /></el-icon>
                      </div>
                      <span class="font-medium leading-relaxed">Área de trabajo completamente <strong class="text-blue-900 dark:text-blue-100">limpia y libre de escombro</strong>.</span>
                  </li>
              </ul>
            </div>
            <div class="text-xs text-blue-600/70 font-medium dark:text-blue-400/70">
                * Construmax de Occidente validará estos puntos antes de procesar la estimación correspondiente.
            </div>
          </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Check, 
    RefreshLeft, 
    Camera,
    Delete,
    Calendar,
    Timer,
    Lock,
    Select,
    DocumentChecked,
    Warning,
    List,
    PictureFilled,
    Picture as IconPicture,
    Document,
    Money,
    ChatDotSquare,
    VideoCamera,
    View
} from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import dayjs from 'dayjs'; 
import 'dayjs/locale/es';

// Configurar locale español globalmente para dayjs
dayjs.locale('es');

const props = defineProps({
  ticket: Object,
  technician: Object,
  tasks: Array,
  workAcceptanceReportUrls: {
    type: Object,
    default: null,
  },
  workAcceptanceReportData: {
    type: Object,
    default: null,
  },
});

const togglingTaskId = ref(null);
const savingNotesId = ref(null);

// Per-task evidence tracking
const pendingEvidence = reactive({});
const uploadingTaskIds = reactive({});

// --- Work acceptance report editable fields ---
const reportForm = reactive({
    work_description: props.workAcceptanceReportData?.work_description || '',
    on_site_start: props.workAcceptanceReportData?.on_site_start
        ? new Date(props.workAcceptanceReportData.on_site_start)
        : '',
    on_site_end: props.workAcceptanceReportData?.on_site_end
        ? new Date(props.workAcceptanceReportData.on_site_end)
        : '',
    technician_comments: props.workAcceptanceReportData?.technician_comments || '',
});
const savingReport = ref(false);

const isReportSigned = computed(() => props.workAcceptanceReportData?.is_signed || false);

async function saveReport() {
    if (!props.workAcceptanceReportData?.update_url) {
        return;
    }
    savingReport.value = true;
    try {
        await axios.put(props.workAcceptanceReportData.update_url, {
            work_description: reportForm.work_description,
            on_site_start: reportForm.on_site_start || null,
            on_site_end: reportForm.on_site_end || null,
            technician_comments: reportForm.technician_comments,
        });
        ElMessage.success('Acta de recepción actualizada correctamente.');
    } catch (err) {
        const msg = err.response?.data?.message || 'Error al guardar los cambios.';
        ElMessage.error(msg);
    } finally {
        savingReport.value = false;
    }
}

const saveNotes = (task) => {
    savingNotesId.value = task.id;
    router.put(task.urls.notes, {
        technician_notes: task.technician_notes,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Notas guardadas');
            savingNotesId.value = null;
        },
        onError: () => {
            ElMessage.error('Error al guardar notas');
            savingNotesId.value = null;
        },
    });
};

// Lógica de Bloqueo Secuencial
const isTaskLocked = (index) => {
    if (index === 0) return false;
    const previousTask = props.tasks[index - 1];
    return previousTask.status !== 'Completada';
};

// --- HELPERS ---

const getStepColorClass = (task, index) => {
    if (task.status === 'Completada') return 'bg-emerald-500 text-white border-white';
    if (isTaskLocked(index)) return 'bg-gray-100 text-gray-400 border-white';
    return 'bg-blue-600 text-white border-blue-50'; 
};

const getStatusType = (status) => {
    const map = { 'Pendiente': 'info', 'En proceso': 'primary', 'Completada': 'success' };
    return map[status] || 'info';
};

const getPriorityColor = (priority) => {
    const map = { 'Alta': 'danger', 'Media': 'warning', 'Baja': 'success' };
    return map[priority] || 'info';
};

// Formato Solicitado: "Jueves 12 febrero, 9:00 am"
const formatDate = (date) => {
    if (!date) return '--:--';
    // dddd=Día nombre, D=Día numero, MMMM=Mes nombre, h:mm a=hora am/pm
    const formatted = dayjs(date).format('dddd D MMMM, h:mm a');
    // Capitalizar primera letra
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
};

// Helper para obtener todas las URLs de imágenes de una tarea específica
// Esto permite navegar (next/prev) entre todas las fotos de ESA tarea en el visor
// Los videos se abren en nueva pestaña
const getTaskImageUrls = (task) => {
    if (!task.media) return [];
    return task.media.filter(m => m.mime_type?.startsWith('image/')).map(m => m.original_url);
};

// --- PAGOS A TÉCNICO ---
const formatPaymentCurrency = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.ticket.budget?.currency || 'MXN',
    }).format(value || 0);
};

const totalTechnicianAmount = computed(() => {
    if (!props.ticket.budget?.concepts) return 0;
    return props.ticket.budget.concepts
        .filter(c => c.paid_to_technician)
        .reduce((sum, c) => sum + parseFloat(c.amount), 0);
});

const totalTechnicianPaid = computed(() => {
    if (!props.ticket.budget?.technician_payments) return 0;
    return props.ticket.budget.technician_payments.reduce((sum, p) => sum + parseFloat(p.amount), 0);
});

const technicianPaymentProgress = computed(() => {
    if (totalTechnicianAmount.value <= 0) return 100;
    return Math.min(Math.round((totalTechnicianPaid.value / totalTechnicianAmount.value) * 100), 100);
});

// --- ACCIONES ---

const toggleStatus = (task) => {
    togglingTaskId.value = task.id;
    router.put(task.urls.toggle, {}, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(task.status === 'Completada' ? 'Tarea reabierta exitosamente' : 'Tarea finalizada correctamente');
            togglingTaskId.value = null;
        },
        onError: () => {
            ElMessage.error('Error al actualizar el estado de la tarea');
            togglingTaskId.value = null;
        }
    });
};

const handleFileSelect = (file, task) => {
    const raw = file.raw;
    const isImage = raw.type?.startsWith('image/');
    const isVideo = raw.type?.startsWith('video/');
    const isLt50M = raw.size / 1024 / 1024 < 50;

    if (!isImage && !isVideo) {
        ElMessage.error('Solo se permiten imágenes y videos.');
        return;
    }

    if (!isLt50M) {
        ElMessage.error('El archivo no debe exceder los 50MB.');
        return;
    }

    if (!pendingEvidence[task.id]) {
        pendingEvidence[task.id] = [];
    }
    pendingEvidence[task.id].push(raw);
};

const flushUpload = (task) => {
    const files = pendingEvidence[task.id];
    if (!files || files.length === 0) return;

    uploadingTaskIds[task.id] = true;

    const formData = new FormData();
    files.forEach(file => formData.append('files[]', file));

    axios.post(task.urls.evidence, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }).then(() => {
        delete pendingEvidence[task.id];
        ElMessage.success('Archivos subidos correctamente');
        router.reload({ preserveScroll: true, preserveState: true });
    }).catch(() => {
        ElMessage.error('Ocurrió un error al subir los archivos');
    }).finally(() => {
        delete uploadingTaskIds[task.id];
    });
};

const deleteEvidence = (url) => {
    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Imagen eliminada correctamente')
    });
};

const showImage = (url) => {
    window.open(url, '_blank');
};
</script>

<style scoped>
/* Ajustes específicos para Element Plus en este contexto */
:deep(.el-card__header) {
    padding: 20px 24px 0;
    border-bottom: none;
}

:deep(.el-card__body) {
    padding: 24px;
}

/* Dark mode overrides for Element Plus card and other components */
:deep(.el-card) {
    --el-card-bg-color: transparent;
}

:deep(.el-input__wrapper) {
    @apply dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100;
}

:deep(.el-input__inner) {
    @apply dark:text-gray-100;
}

:deep(.el-textarea__inner) {
    @apply dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 dark:placeholder-gray-400;
}

/* Step badge in dark mode (dynamic classes from getStepColorClass) */
.step-badge.border-white {
    @apply dark:border-gray-800;
}

.step-badge.bg-gray-100 {
    @apply dark:bg-gray-700;
}

.step-badge.text-gray-400 {
    @apply dark:text-gray-500;
}
</style>