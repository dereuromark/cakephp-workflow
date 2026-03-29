import { defineConfig } from 'vitepress'
import { readFileSync } from 'fs'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const neonGrammar = JSON.parse(
  readFileSync(resolve(__dirname, 'grammars/neon.tmLanguage.json'), 'utf-8')
)

export default defineConfig({
  title: 'cakephp-workflow',
  description: 'State machine and workflow engine for CakePHP with attributes, YAML/NEON support, and admin tooling.',
  base: '/cakephp-workflow/',
  head: [
    ['link', { rel: 'icon', href: '/cakephp-workflow/favicon.svg', type: 'image/svg+xml' }],
  ],
  markdown: {
    languages: [
      {
        ...neonGrammar,
        name: 'neon',
        aliases: ['NEON'],
      },
    ],
  },
  themeConfig: {
    logo: '/logo.svg',
    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/guide/' },
      { text: 'Definitions', link: '/definitions/', activeMatch: '/definitions/' },
      { text: 'Admin', link: '/admin/', activeMatch: '/admin/' },
      { text: 'Cookbook', link: '/cookbook/', activeMatch: '/cookbook/' },
      { text: 'Reference', link: '/reference/api', activeMatch: '/reference/' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/dereuromark/cakephp-workflow' },
          { text: 'Packagist', link: 'https://packagist.org/packages/dereuromark/cakephp-workflow' },
          { text: 'Issues', link: 'https://github.com/dereuromark/cakephp-workflow/issues' },
        ],
      },
    ],
    sidebar: {
      '/guide/': [
        {
          text: 'Guide',
          items: [
            { text: 'Getting Started', link: '/guide/' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Quick Start', link: '/guide/quick-start' },
            { text: 'Behavior Integration', link: '/guide/behavior' },
            { text: 'Events, Logging, and Locks', link: '/guide/runtime' },
          ],
        },
      ],
      '/definitions/': [
        {
          text: 'Definitions',
          items: [
            { text: 'Overview', link: '/definitions/' },
            { text: 'Attributes', link: '/definitions/attributes' },
            { text: 'NEON and YAML', link: '/definitions/config-files' },
            { text: 'States and Transitions', link: '/definitions/states-and-transitions' },
            { text: 'Automatic Transitions', link: '/definitions/automatic-transitions' },
          ],
        },
      ],
      '/admin/': [
        {
          text: 'Admin',
          items: [
            { text: 'Dashboard', link: '/admin/' },
            { text: 'Workflow Views', link: '/admin/workflow-views' },
            { text: 'Validation and Orphans', link: '/admin/validation' },
            { text: 'Timeouts and Locks', link: '/admin/operations' },
          ],
        },
      ],
      '/cookbook/': [
        {
          text: 'Cookbook',
          items: [
            { text: 'Recipes', link: '/cookbook/' },
            { text: 'Order Workflow', link: '/cookbook/order-workflow' },
            { text: 'Approval Flow', link: '/cookbook/approval-flow' },
            { text: 'Testing Workflows', link: '/cookbook/testing' },
            { text: 'Scaffolding', link: '/cookbook/scaffolding' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'API', link: '/reference/api' },
            { text: 'CLI', link: '/reference/cli' },
            { text: 'Architecture', link: '/reference/architecture' },
            { text: 'Comparison and Gaps', link: '/reference/comparison' },
          ],
        },
      ],
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/dereuromark/cakephp-workflow' },
    ],
    search: {
      provider: 'local',
    },
    editLink: {
      pattern: 'https://github.com/dereuromark/cakephp-workflow/edit/master/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright Mark Scherer',
    },
  },
})
