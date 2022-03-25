module.exports = {
  apps: [{
    name: 'xummlmspayout',
    script: 'index.js',
    watch: false,
    instances: 1,
    exec_mode: 'cluster',
    ignore_watch: ["node_modules", "db", ".git"],
    args: ["--color"],
    env: {
      DEBUG: 'xummlmspayout*'
    },
    env_production: {
      DEBUG: 'xummlmspayout*'
    }
  }]
}