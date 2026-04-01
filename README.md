# Cloudflare WAF 批量部署工具 v1.0 (正式版) 🚀

[![Language](https://img.shields.io/badge/Language-Python-blue)](https://www.python.org/)
[![Cloudflare](https://img.shields.io/badge/Provider-Cloudflare-orange)](https://www.cloudflare.com/)

这是一款面向中大型站长、运维人员设计的 Cloudflare WAF 规则批量管理工具。通过智能算法优化 API 请求，支持数百个域名的一键同步与防御部署。
<div style="text-align: left;">
  <img src="https://github.com/user-attachments/assets/f5767086-9d39-485a-ab89-99ee41f7bede" alt="WP 一键备份还原" width="60%">
</div>
---

## ✨ v1.0 核心突破

### 1. ⚡ 极致表达式优化 (Expression Folding)
针对“放行代理 IP”规则，工具会自动将分散的单一 IP 聚合成 `ip.src in { ... }` 格式。
- **容量提升**：单条规则可容纳的 IP 数量提升了 400% 以上。
- **性能更佳**：减少 Cloudflare 边缘节点的匹配开销。
- **智能识别**：自动区分单 IP (eq) 与 CIDR 网段 (in)。

### 2. 🧠 智能批处理引擎 (Hybrid Mode)
内置两种规则清理策略，并支持根据规则负载自动切换：
- **逐条删除 (Standard)**：适用于小规模规则集，过程透明、稳健。
- **批量替换 (High Performance)**：通过单次 `PUT` 请求重置整个规则集。当域名下的自定义规则超过设定阈值（默认 20 条）时自动触发，部署速度提升 10 倍。

### 3. 🔍 全局一致性预检 (Consistency Check)
在执行任何修改前，工具会抓取云端现有规则进行深度 MD5 比对。若规则已符合预期，将自动跳过，实现 **“无感部署”** 与 **“零冗余 API 调用”**。

---

## ⚙️ 服务器环境配置建议 (重要)

为确保工具稳定运行，建议您的 Python 环境及服务器满足以下要求：
- **Python 3.9+** (推荐)
- **依赖库**：`requests`

### 编译 EXE 建议
为了获得最佳的启动性能与兼容性，建议使用 **Nuitka** 编译：
```bash
python -m nuitka --standalone --onefile --windows-disable-console --plugin-enable=tk-inter CloudflareWAFApp.py
```

---

## 🛠️ 快速上手

1. **认证配置**：支持 **API Token**（推荐）或 Global Key。
2. **规则配置**：
   - **规则 1**：输入需要放行的 VPS/办公网 IP（支持 CIDR）。
   - **规则 2**：一键开启良性爬虫（Google/Bing 等）豁免。
   - **规则 3**：部署海外流量托管质询，内置中国（含港澳台）白名单排除逻辑。
3. **高级选项**：可自定义批量操作阈值及一致性检查开关。
4. **一键部署**：点击“开始部署”，实时监控带时间戳的详细日志。

---

## 📄 许可证
GPL v2 or later

---
**Developed by Stone** | 打造 WordPress + Cloudflare 的最强运维护城河。
