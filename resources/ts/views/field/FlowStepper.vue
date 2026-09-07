<script setup lang="ts">
/**
 * Üst adım şeridi: hangi evrede (Gün Başlangıcı / Etkinlik / Gün Sonu) ve hangi adımda olunduğunu gösterir.
 */
withDefaults(defineProps<{
  phaseLabel: string
  steps: string[]
  current: number
  color?: string
}>(), {
  color: 'primary',
})
</script>

<template>
  <div class="flow-stepper">
    <div class="flow-stepper__phase text-caption text-uppercase font-weight-bold">
      {{ phaseLabel }}
    </div>
    <div class="flow-stepper__track">
      <template
        v-for="(label, index) in steps"
        :key="label"
      >
        <div
          class="flow-stepper__step"
          :class="{
            'flow-stepper__step--done': index + 1 < current,
            'flow-stepper__step--active': index + 1 === current,
          }"
        >
          <VAvatar
            size="28"
            :color="index + 1 <= current ? color : undefined"
            :variant="index + 1 <= current ? 'flat' : 'tonal'"
          >
            <VIcon
              v-if="index + 1 < current"
              icon="tabler-check"
              size="16"
            />
            <span
              v-else
              class="text-caption font-weight-bold"
            >{{ index + 1 }}</span>
          </VAvatar>
          <div class="flow-stepper__label text-caption">
            {{ label }}
          </div>
        </div>
        <div
          v-if="index < steps.length - 1"
          class="flow-stepper__line"
          :class="{ 'flow-stepper__line--done': index + 1 < current }"
        />
      </template>
    </div>
  </div>
</template>

<style scoped>
.flow-stepper {
  padding: 8px 12px 4px;
}

.flow-stepper__phase {
  letter-spacing: 0.08em;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-block-end: 6px;
}

.flow-stepper__track {
  display: flex;
  align-items: flex-start;
}

.flow-stepper__step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 64px;
  text-align: center;
  color: rgba(var(--v-theme-on-surface), 0.55);
}

.flow-stepper__step--active {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
}

.flow-stepper__step--done {
  color: rgba(var(--v-theme-on-surface), 0.75);
}

.flow-stepper__label {
  line-height: 1.2;
}

.flow-stepper__line {
  flex: 1;
  height: 2px;
  margin-block-start: 13px;
  background: rgba(var(--v-theme-on-surface), 0.14);
}

.flow-stepper__line--done {
  background: rgb(var(--v-theme-success));
}
</style>
