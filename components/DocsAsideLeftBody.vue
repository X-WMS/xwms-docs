<script setup lang="ts">
import type { ContentNavigationItem } from '@nuxt/content'

const rawNavigation = inject<Ref<ContentNavigationItem[]>>('navigation')

const normalizeNavigation = (items: ContentNavigationItem[] = []): ContentNavigationItem[] =>
  items.map((item) => {
    const collapsedFlag =
      (item as any).collapsed ??
      (item as any).navigation?.collapsed ??
      false

    return {
      ...(item as any),
      // optional: bewaren als eigen flag
      collapsed: collapsedFlag,
      // Belangrijk: UContentNavigation gebruikt `defaultOpen`
      // Als collapsed = true, dan moet defaultOpen = false zijn
      defaultOpen: collapsedFlag ? false : (item as any).defaultOpen,
      children: item.children ? normalizeNavigation(item.children) : undefined,
    }
  })

const navigation = computed(() =>
  rawNavigation?.value ? normalizeNavigation(rawNavigation.value) : []
)
</script>

<template>
  <UContentNavigation
    highlight
    :navigation="navigation"
  />
</template>

