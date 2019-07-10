# 下载vue-element-admin
```
 git clone https://github.com/PanJiaChen/vue-element-admin.git
```
# 创建 Laravel5.8 项目
```
composer create-project --prefer-dist laravel/laravel laravel-element-admin "5.8.*"
```
# 复制文件
将 vue-element-admin 中整个 src 目录下的文件复制到 laravel 项目中的 resources/backend 目录中。  
# 安装前端依赖
```
sudo npm install  --unsafe-perm
```
# 配置路由映射
```
在 routes/web.php 文件中添加如下路由：

Route::get('/admin', function () {
    return view('admin');
});
```

# 配置nginx http://laravelelement.test 指向项目public/目录
# admin.blade.php
```
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>title</title>
</head>
<body>
<div id="app"></div>
<script src="{{ mix('/js/manifest.js') }}"></script>
<script src="{{ mix('/js/vendor.js') }}"></script>
<script src="{{ mix('/js/main.js') }}"></script>
</body>
</html>
```
# webpack.mix.js
```
const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

Mix.listen('configReady', (webpackConfig) => {
  // Exclude 'svg' folder from font loader
  let fontLoaderConfig = webpackConfig.module.rules.find(rule => String(rule.test) === String(/(\.(png|jpe?g|gif|webp)$|^((?!font).)*\.svg$)/));
  fontLoaderConfig.exclude = /(resources\/backend\/icons)/;
});

mix.webpackConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/backend'),
    }
  },
  module: {
    rules: [
      {
        test: /\.svg$/,
        loader: 'svg-sprite-loader',
        include: [path.resolve(__dirname, 'resources/backend/icons/svg')],
        options: {
          symbolId: 'icon-[name]'
        }
      }
    ],
  }
}).babelConfig({
  plugins: ['dynamic-import-node']
});

mix.js('resources/js/app.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css');
//分开打包
mix.js('resources/backend/main.js', 'public/js').extract(['vue', 'axios']);
//添加随机参数，保障文件更新
if (mix.inProduction()) {
  mix.version();
}
```
# package.json
```
{
  "name": "vue-element-admin",
  "version": "4.2.1",
  "description": "A magical vue admin. An out-of-box UI solution for enterprise applications. Newest development stack of vue. Lots of awesome features",
  "author": "Pan <panfree23@gmail.com>",
  "license": "MIT",
  "scripts": {
    "dev": "vue-cli-service serve",
    "build:prod": "vue-cli-service build",
    "build:stage": "vue-cli-service build --mode staging",
    "preview": "node build/index.js --preview",
    "lint": "eslint --ext .js,.vue src",
    "test:unit": "jest --clearCache && vue-cli-service test:unit",
    "test:ci": "npm run lint && npm run test:unit",
    "svgo": "svgo -f src/icons/svg --config=src/icons/svgo.yml",
    "new": "plop"
  },
  "husky": {
    "hooks": {
      "pre-commit": "lint-staged"
    }
  },
  "lint-staged": {
    "src/**/*.{js,vue}": [
      "eslint --fix",
      "git add"
    ]
  },
  "keywords": [
    "vue",
    "admin",
    "dashboard",
    "element-ui",
    "boilerplate",
    "admin-template",
    "management-system"
  ],
  "repository": {
    "type": "git",
    "url": "git+https://github.com/PanJiaChen/vue-element-admin.git"
  },
  "bugs": {
    "url": "https://github.com/PanJiaChen/vue-element-admin/issues"
  },
  "dependencies": {
    "axios": "0.18.1",
    "clipboard": "2.0.4",
    "codemirror": "5.45.0",
    "driver.js": "0.9.5",
    "dropzone": "5.5.1",
    "echarts": "4.2.1",
    "element-ui": "2.7.0",
    "file-saver": "2.0.1",
    "fuse.js": "3.4.4",
    "js-cookie": "2.2.0",
    "jsonlint": "1.6.3",
    "jszip": "3.2.1",
    "normalize.css": "7.0.0",
    "nprogress": "0.2.0",
    "path-to-regexp": "2.4.0",
    "screenfull": "4.2.0",
    "showdown": "1.9.0",
    "sortablejs": "1.8.4",
    "tui-editor": "1.3.3",
    "vue": "2.6.10",
    "vue-count-to": "1.0.13",
    "vue-router": "3.0.2",
    "vue-splitpane": "1.0.4",
    "vuedraggable": "2.20.0",
    "vuex": "3.1.0",
    "xlsx": "0.14.1"
  },
  "devDependencies": {
    "@babel/core": "7.0.0",
    "@babel/register": "7.0.0",
    "@vue/cli-plugin-babel": "3.5.3",
    "@vue/cli-plugin-eslint": "^3.9.1",
    "@vue/cli-plugin-unit-jest": "3.5.3",
    "@vue/cli-service": "3.5.3",
    "@vue/test-utils": "1.0.0-beta.29",
    "autoprefixer": "^9.5.1",
    "babel-core": "7.0.0-bridge.0",
    "babel-eslint": "10.0.1",
    "babel-jest": "23.6.0",
    "chalk": "2.4.2",
    "chokidar": "2.1.5",
    "connect": "3.6.6",
    "eslint": "5.15.3",
    "eslint-plugin-vue": "5.2.2",
    "html-webpack-plugin": "3.2.0",
    "husky": "1.3.1",
    "lint-staged": "8.1.5",
    "mockjs": "1.0.1-beta3",
    "node-sass": "^4.9.0",
    "plop": "2.3.0",
    "runjs": "^4.3.2",
    "sass-loader": "^7.1.0",
    "script-ext-html-webpack-plugin": "2.1.3",
    "script-loader": "0.7.2",
    "serve-static": "^1.13.2",
    "svg-sprite-loader": "4.1.3",
    "svgo": "1.2.0",
    "vue-template-compiler": "2.6.10"
  },
  "engines": {
    "node": ">=8.9",
    "npm": ">= 3.0.0"
  },
  "browserslist": [
    "> 1%",
    "last 2 versions"
  ]
}

```

# 执行npm run dev (以下表示成功)
```
 DONE  Compiled successfully in 24931ms                                                                                                                                                         5:45:51 PM

                                                                                      Asset      Size        Chunks             Chunk Names
                                                                               /css/app.css   191 KiB       /js/app  [emitted]  /js/app
                                                                                 /js/app.js  1.03 MiB       /js/app  [emitted]  /js/app
                                                                                /js/main.js    14 MiB      /js/main  [emitted]  /js/main
                                                                            /js/manifest.js  6.05 KiB  /js/manifest  [emitted]  /js/manifest
                                                                              /js/vendor.js   375 KiB    /js/vendor  [emitted]  /js/vendor
                                   fonts/element-icons.ttf?27c72091ab590fb5d1c3ef90f988ddce  10.8 KiB                [emitted]
                                  fonts/element-icons.woff?9b70ee41d12a1cf127400d23534f7efc  5.98 KiB                [emitted]
 fonts/vendor/element-ui/lib/theme-chalk/element-icons.ttf?6f0a76321d30f3c8120915e57f7bd77e  10.8 KiB                [emitted]
fonts/vendor/element-ui/lib/theme-chalk/element-icons.woff?2fad952a20fbbcfd1bf2ebb210dccf7a  6.02 KiB                [emitted]
                                            images/401.gif?089007e721e1f22809c0313b670a36f1   160 KiB                [emitted]
                                            images/404.png?a57b6f31fa77c50f14d756711dea4158  95.8 KiB                [emitted]
                                      images/404_cloud.png?0f4bc32b0f52f7cfb7d19305a6517724  4.65 KiB                [emitted]
           images/vendor/tui-editor/dist/tui-editor-2x.png?b4361244b610df3a6c728a26a49f782b  23.9 KiB                [emitted]
              images/vendor/tui-editor/dist/tui-editor.png?30dd0f529e5155cab8a1aefa4716de7f  10.6 KiB                [emitted]
```



# 可能遇见的问题
1. ERROR in ./resources/backend/router/index.js
```
ERROR in ./resources/backend/router/index.js
Module not found: Error: Can't resolve '@/views/zip/index' in '/path/to/laravel-vue-admin/resources/backend/router'
```
解决方法:  
```
在 webpack.mix.js 添加如下代码：

mix.webpackConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/backend'),
    },
  },
})
```
2. 卡在 70% 无法继续
```
> @ dev /path/to/laravel-vue-admin
> npm run development


> @ development /path/to/laravel-vue-admin
> cross-env NODE_ENV=development node_modules/webpack/bin/webpack.js --progress --hide-modules --config=node_modules/laravel-mix/setup/webpack.config.js

 70% building 2069/2069 modules 0 active 
```
解决办法:
```
安装依赖
$ npm install babel-plugin-dynamic-import-node --save-dev

修改 webpack.mix.js
具体如下：

mix.webpackConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/backend'),
    }
  }
})
// 此处新增
.babelConfig({
  plugins: ['dynamic-import-node']
});
```

3. sass 中 /deep/ 解析失败
```
ERROR in ./resources/backend/components/HeaderSearch/index.vue?vue&type=style&index=0&id=6eba4ace&lang=scss&scoped=true& (./node_modules/css-loader!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/postcss-loader/src??ref--7-2!./node_modules/sass-loader/lib/loader.js??ref--7-3!./node_modules/vue-loader/lib??vue-loader-options!./resources/backend/components/HeaderSearch/index.vue?vue&type=style&index=0&id=6eba4ace&lang=scss&scoped=true&)
Module build failed (from ./node_modules/sass-loader/lib/loader.js):

    /deep/ .el-input__inner {
   ^
      Expected selector.
    ╷
172 │     /deep/ .el-input__inner{
    │     ^
    ╵
  stdin 172:5  root stylesheet
```
解决办法:这里应该是版本兼容问题。需要将所有 backend 目录中出现的 /deep/ 替换为 >>>。  
4. mock 资源无法加载
```
ERROR in ./resources/backend/main.js
Module not found: Error: Can't resolve '../mock' in '/path/to/laravel-vue-admin/resources/backend'
 @ ./resources/backend/main.js 31:0-34
 @ multi ./resources/backend/main.js
```
解决办法:
> 这个解决方案看你需要，如果你需要mock接口，那么需要复制原 vue-element-admin 中的 mock 文件夹到 resources/mock 即可。
> 如果不需要，则需要将依赖 mock 资源的代码删掉。
> 涉及 resources/backendmain.js  

5. bable 失败
```
ERROR in ./resources/backend/layout/components/Sidebar/Item.vue?vue&type=script&lang=js& (./node_modules/babel-loader/lib??ref--4-0!./node_modules/vue-loader/lib??vue-loader-options!./resources/backend/layout/components/Sidebar/Item.vue?vue&type=script&lang=js&)
Module build failed (from ./node_modules/babel-loader/lib/index.js):
SyntaxError: /path/to/laravel-vue-admin/resources/backend/layout/components/Sidebar/Item.vue: Unexpected token (20:18)

  18 | 
  19 |     if (icon) {
> 20 |       vnodes.push(<svg-icon icon-class={icon}/>)
     |                   ^
  21 |     }
```

解决办法:经查询为 bable 没有正确配置，直接复制 vue-element-admin 中 babel.config.js 即可。或者在push里面加双引号!
6. 图标不显示
- 覆盖原始图片加载规则  
- 新增svg图片的加载  
```

Mix.listen('configReady', (webpackConfig) => {
  // Exclude 'svg' folder from font loader
  let fontLoaderConfig = webpackConfig.module.rules.find(rule => String(rule.test) === String(/(\.(png|jpe?g|gif|webp)$|^((?!font).)*\.svg$)/));
  fontLoaderConfig.exclude = /(resources\/backend\/icons)/;
});

mix.webpackConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/backend'),
    }
  },
  module: {
    rules: [
      {
        test: /\.svg$/,
        loader: 'svg-sprite-loader',
        include: [path.resolve(__dirname, 'resources/backend/icons/svg')],
        options: {
          symbolId: 'icon-[name]'
        }
      }
    ],
  }
}).babelConfig({
  plugins: ['dynamic-import-node']
});
```

# 到此可以打开 http://laravelelement.test/admin 会出现登录页
> 登录后会出现404,接着继续开始配置

# /routes/web.php配置如下,倒腾(调试)了好久才知道正确的配置

```
Route::get('/admin', function () {
    return view('admin');
});
Route::post('/user/login', function () { 
//先返回默认值看看效果
    return [
        'data'=>
            [
                'roles'=> ['admin'],
                'introduction'=> 'I am a super administrator',
                'avatar'=> 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
                'name'=> 'Super Admin',
                'token'=>'admin-token'
            ],
        'code'=>20000
    ];
});
Route::get('/user/info', function () {
//先返回默认值看看效果
    return [
        'data'=>
            [
                'roles'=> ['admin'],
                'introduction'=> 'I am a super administrator',
                'avatar'=> 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
                'name'=> 'Super Admin',
                'token'=>'admin-token'
            ],
        'code'=>20000
    ];
});
Route::get('/transaction/list', function () {
//先返回默认值看看效果
    return [
        'data'=>
            [
                'roles'=> ['admin'],
                'introduction'=> 'I am a super administrator',
                'avatar'=> 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
                'name'=> 'Super Admin',
                'token'=>'admin-token',
                'items'=>[]
            ],
        'code'=>20000
    ];
});

Route::get('/user/logout', function () {
//先返回默认值看看效果
    return [
        'data'=>'success',
        'code'=>20000
    ];
});
```
# 再登录即可登录进入主页

# 总结:心累,一路报错才搞下来!