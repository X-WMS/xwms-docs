<script setup lang="ts">
import type { ContentNavigationItem } from '@nuxt/content'
const navigation = inject<Ref<ContentNavigationItem[]>>('navigation')

// console.log('DocsAsideLeftBody override geladen')
// console.log('navigation data:', navigation.value)
</script>

<template>
  <UContentNavigation
    highlight
    :navigation="navigation"
  />
</template>

