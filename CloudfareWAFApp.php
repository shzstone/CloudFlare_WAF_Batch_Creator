import tkinter as tk
from tkinter import scrolledtext, messagebox, ttk, Menu
import threading
import requests
import time
import datetime
from enum import Enum
import webbrowser
import json


class AuthMethod(Enum):
    """认证方式枚举"""
    GLOBAL_KEY = "Global API Key"
    API_TOKEN = "API Token"


class OperationMode(Enum):
    """规则操作模式枚举"""
    AUTO = "自动选择 (推荐)"
    DELETE_ONE_BY_ONE = "逐条删除"
    BATCH_REPLACE = "批量替换"


class CloudflareWAFApp:
    def __init__(self, root):
        self.root = root
        self.root.title("Cloudflare WAF 批量部署工具 v1.0")
        self.root.geometry("800x850")  # 增加窗口高度以适应新的UI元素

        # 创建菜单栏
        self.create_menu()

        # 认证方式变量
        self.auth_method = tk.StringVar(value=AuthMethod.API_TOKEN.value)  # 默认使用 API Token
        self.email = tk.StringVar()
        self.api_key = tk.StringVar()
        self.api_token = tk.StringVar()

        # 规则开关变量
        self.enable_rule1 = tk.BooleanVar(value=True)
        self.enable_rule2 = tk.BooleanVar(value=True)
        self.enable_rule3 = tk.BooleanVar(value=True)

        # 规则3国家/地区变量
        self.include_cn = tk.BooleanVar(value=True)  # 中国大陆
        self.include_hk = tk.BooleanVar(value=True)  # 香港
        self.include_mo = tk.BooleanVar(value=True)  # 澳门
        self.include_tw = tk.BooleanVar(value=True)  # 台湾
        self.additional_countries = tk.StringVar(value="")  # 额外国家

        # 高级选项
        self.enable_consistency_check = tk.BooleanVar(value=True)  # 默认启用一致性检查
        self.operation_mode = tk.StringVar(value=OperationMode.AUTO.value)  # 操作模式
        self.batch_threshold = tk.IntVar(value=20)  # 批量操作阈值

        self.create_widgets()
        # 绑定认证方式变更事件
        self.auth_method.trace_add('write', self.on_auth_method_change)
        # 初始化UI状态
        self.on_auth_method_change()

    def create_menu(self):
        """创建菜单栏"""
        menubar = Menu(self.root)
        self.root.config(menu=menubar)

        # 文件菜单
        file_menu = Menu(menubar, tearoff=0)
        menubar.add_cascade(label="文件", menu=file_menu)
        file_menu.add_command(label="退出", command=self.root.quit)

        # 帮助菜单
        help_menu = Menu(menubar, tearoff=0)
        menubar.add_cascade(label="帮助", menu=help_menu)
        help_menu.add_command(label="使用说明", command=self.show_help)
        help_menu.add_separator()
        help_menu.add_command(label="API Token 申请指南", command=self.open_api_token_guide)
        help_menu.add_command(label="关于", command=self.show_about)

    def show_help(self):
        """显示使用说明"""
        help_window = tk.Toplevel(self.root)
        help_window.title("使用说明")
        help_window.geometry("700x600")
        help_window.resizable(True, True)

        # 创建滚动文本框
        help_text = scrolledtext.ScrolledText(help_window, wrap=tk.WORD, width=80, height=30)
        help_text.pack(fill=tk.BOTH, expand=True, padx=10, pady=10)

        # 插入帮助内容
        help_content = """=== Cloudflare WAF 批量部署工具 使用说明 ===

1. 工具简介
本工具是一个图形化（GUI）应用程序，用于帮助您批量、快速地在 Cloudflare 账户下的所有域名中部署自定义的 WAF（Web 应用防火墙）规则。它简化了通过 Cloudflare API 手动操作的繁琐过程。

2. 准备工作：获取 Cloudflare 认证凭据
工具支持两种认证方式，我们强烈推荐使用更安全的 API Token。

2.1 推荐：申请 API Token（更安全）
API Token 权限可以精确控制，泄露风险低。

1. 登录您的 Cloudflare 仪表盘。
2. 点击右上角的头像，选择 "My Profile"。
3. 在左侧菜单栏选择 "API Tokens"。
4. 点击 "Create Token"。
5. 在模板列表中选择 "Edit zone DNS" 模板，或选择 "Create Custom Token" 自定义创建。
6. 配置权限（关键步骤！）：
   a. 权限1：选择 "Zone" -> "DNS" -> "Read"。
      - 用途：必需，允许工具读取您账户下的所有域名列表。
      - 资源范围：选择 "All zones"。
   b. 点击 "Add more" 添加第二个权限。
   c. 权限2：选择 "Zone" -> "WAF" -> "Edit"。
      - 用途：必需，允许工具在域名上创建、修改、删除 WAF 规则。
      - 资源范围：选择 "All zones"。
7. 设置资源范围：
   a. 在 "Account Resources" 部分，选择您的主账户。
   b. 在 "Zone Resources" 部分，必须选择 "All zones"。
      - 重要：必须选择 "All zones"，工具才能批量修改所有域名。
8. 点击 "Continue to summary"，为令牌命名（例如"WAF-Bulk-Deploy-Tool"）。
9. 点击 "Create Token"。
10. 非常重要：复制生成的令牌字符串并妥善保存。此令牌只会显示一次。

正确的 API Token 权限总结（基于您当前可用的 Token 配置）：
- Zone - DNS - Read (作用于 所有区域) [必需，用于读取域名列表]
- Zone - WAF - Edit (作用于 所有区域) [必需，用于修改 WAF 规则]

注意：如果您的 Token 配置中不包含 Account 级别的 "Read" 权限，但拥有 "所有区域" 的 "Zone DNS:Read" 和 "Zone WAF:Edit" 权限，工具通常仍然能正常工作，因为 Cloudflare 的权限系统可能会有特殊处理。

2.2 备选：使用 Global API Key（权限过大，不推荐）
Global API Key 拥有账户的完全控制权，一旦泄露后果严重。

1. 登录您的 Cloudflare 仪表盘。
2. 点击右上角的头像，选择 "My Profile"。
3. 在左侧菜单栏选择 "API Tokens"。
4. 页面下方找到 "API Keys" 区域，点击 "Global API Key" 旁边的 "View"。
5. 输入您的账户密码进行验证。
6. 复制显示的 Global API Key。

3. 工具界面填写指南

3.1 【Cloudflare 认证】区域
- 认证方式：在下拉框中选择。
  - 推荐选择 "API Token"（默认）。
  - 如果选择 "Global API Key"，下方会出现"邮箱"输入框。
- 邮箱：仅在您选择 Global API Key 时可见。请填写您 Cloudflare 账户的注册邮箱。
- API Token 输入框：当选择 API Token 时可见，用于粘贴您的 API Token。
- Global API Key 输入框：当选择 Global API Key 时可见，用于粘贴您的 Global API Key。

3.2 【规则1: 放行代理 IP】区域
- 用途：为您指定的 IP 地址或 IP 段（例如您的服务器、办公网络）创建放行规则，使其绕过 Cloudflare 的各种安全挑战和 WAF 检查。
- 填写格式：
  - 每行填写一个 IP 地址或 CIDR 格式的网段。
  - 支持格式：
    - 单个 IPv4 地址：192.0.2.1
    - IPv4 CIDR 网段：192.0.2.0/24
    - 单个 IPv6 地址：2001:db8::1
    - IPv6 CIDR 网段：2001:db8::/32
  - 示例：
    203.0.113.5
    198.51.100.0/24
    2001:db8:cafe::1

3.3 【规则开关】
- 启用规则1: 放行代理 IP：勾选后，将根据上方文本框的内容创建放行规则。
- 启用规则2: 放行已知自动程序：勾选后，将创建一条规则，允许 Google、Bing 等已知的良性网络爬虫（Bot）绕过速率限制，确保您的网站内容能被正常收录。

3.4 【规则3: 海外流量托管质询】
- 用途：对来自特定国家/地区的访问者，触发 Cloudflare 的托管质询（Managed Challenge，通常是验证码）。
- 配置方式：
  a. 默认排除的国家/地区（建议勾选）：
    - 中国大陆
    - 香港
    - 澳门
    - 台湾
  b. 您可以取消勾选不需要排除的国家/地区。
  c. 额外指定国家/地区：在输入框中可以指定其他要排除的国家/地区代码。
- 国家/地区代码格式：
  - 使用 ISO 3166-1 alpha-2 国家代码（两位字母代码）
  - 多个国家/地区代码用英文逗号分隔
  - 示例：US,JP,KR,GB
- 功能说明：规则将对除了您指定排除的国家/地区之外的所有访问者触发托管质询。

3.5 【高级选项】
- 启用一致性检查：勾选后，工具会在部署前检查现有规则与要创建的规则是否完全一致。如果完全一致，则跳过该域名，避免不必要的操作。这可以提高部署效率并减少对 Cloudflare API 的调用。
- 规则操作模式：
  - 自动选择 (推荐)：当现有规则数量超过阈值时，自动使用批量替换模式，否则使用逐条删除模式。
  - 逐条删除：逐一删除每条现有规则，适合规则数量较少的情况。
  - 批量替换：使用 PUT 请求一次性替换整个规则集，适合规则数量较多的情况，效率更高。
- 批量操作阈值：当规则数量超过此值时，自动选择模式会切换到批量替换。默认值为20。

4. 使用步骤
1. 填写认证信息：按照第 2、3.1 部分说明，选择认证方式并填写凭据。
2. 配置 IP 列表：在 3.2 部分的文本框中，按格式输入您需要放行的 IP 地址。
3. 选择启用规则：在 3.3 部分勾选您需要部署的规则。规则1必须填写 IP 后才能启用。
4. 配置规则3：在 3.4 部分选择要排除的国家/地区，并可指定额外国家/地区代码。
5. 配置高级选项：在 3.5 部分选择是否启用一致性检查和规则操作模式。
6. 开始部署：点击 "开始部署" 按钮。
7. 查看日志：部署过程会实时显示在底部的日志窗口中。带有时间戳的日志会详细记录每一个步骤，包括获取域名、处理每个域名的进度、API 调用状态（成功/失败/重试）等。
8. 等待完成：程序会自动遍历您账户下的所有域名，依次检查、删除旧的规则并创建新规则。所有操作完成后，日志末尾会显示摘要。

5. 注意事项与常见问题
- 安全性：请妥善保管您的 API Token 或 Global API Key，不要分享给他人或提交到公开的代码仓库。
- 覆盖操作：每次运行工具，都会先删除目标域名下自定义 WAF 规则集中的所有现有规则，然后创建您当前勾选的新规则。请确保这是您想要的操作。
- 多域名：工具会自动处理您账户下的所有域名，没有数量限制。
- 网络问题：工具内置了重试机制。如果遇到短暂的网络波动或 Cloudflare API 速率限制，它会自动等待一段时间后重试，以提高部署成功率。
- 一致性检查：启用一致性检查后，工具会比较现有规则与要创建的规则。如果完全一致，则跳过该域名，避免不必要的修改。
- 操作模式：对于拥有大量规则的用户，建议使用批量替换模式以提高效率。
- 日志解读：日志中会包含时间戳和成功(✓)/失败(✗)标志。如果部署失败，请根据日志中提示的错误信息（如认证失败、权限不足、IP格式错误等）进行排查。
"""

        help_text.insert(tk.END, help_content)
        help_text.config(state=tk.DISABLED)  # 设置为只读

        # 添加关闭按钮
        close_btn = ttk.Button(help_window, text="关闭", command=help_window.destroy)
        close_btn.pack(pady=10)

    def open_api_token_guide(self):
        """打开 API Token 申请指南网页"""
        webbrowser.open("https://developers.cloudflare.com/fundamentals/api/get-started/create-token/")

    def open_country_codes_guide(self):
        """打开国家代码查询指南"""
        webbrowser.open("https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2")

    def show_about(self):
        """显示关于窗口"""
        about_text = """Cloudflare WAF 批量部署工具 v1.0

版本: 1.0 正式版
描述: 批量部署 Cloudflare WAF 规则的专业工具

功能特性:
1. 支持 API Token 和 Global API Key 两种认证方式
2. 智能识别 IP 地址和 CIDR 网段
3. 内置重试机制，提高部署成功率
4. 支持一致性检查，避免不必要的规则修改
5. 可配置的国家/地区排除规则
6. 智能批量操作模式，自动优化性能
7. 详细的日志记录和错误恢复
8. 友好的图形界面

© 2024 保留所有权利"""
        messagebox.showinfo("关于", about_text)

    def create_widgets(self):
        # 认证信息区域
        auth_frame = ttk.LabelFrame(self.root, text="Cloudflare 认证 (默认并推荐使用 API Token)", padding=10)
        auth_frame.pack(fill="x", padx=10, pady=5)

        # 认证方式选择
        ttk.Label(auth_frame, text="认证方式:").grid(row=0, column=0, sticky="w", padx=5, pady=5)
        auth_combo = ttk.Combobox(auth_frame, textvariable=self.auth_method,
                                  values=[m.value for m in AuthMethod], state="readonly", width=20)
        auth_combo.grid(row=0, column=1, sticky="w", padx=5, pady=5)
        ttk.Label(auth_frame, text="(推荐使用 API Token)").grid(row=0, column=2, sticky="w", padx=5, pady=5)

        # 邮箱（Global Key 时显示）
        self.email_label = ttk.Label(auth_frame, text="邮箱:")
        self.email_label.grid(row=1, column=0, sticky="w", padx=5, pady=5)
        self.email_entry = ttk.Entry(auth_frame, textvariable=self.email, width=40)
        self.email_entry.grid(row=1, column=1, columnspan=2, sticky="w", padx=5, pady=5)

        # API Token 输入框 (始终存在，但根据选择显示/隐藏)
        self.token_label = ttk.Label(auth_frame, text="API Token:")
        self.token_label.grid(row=2, column=0, sticky="w", padx=5, pady=5)
        self.token_entry = ttk.Entry(auth_frame, textvariable=self.api_token, width=40, show="")
        self.token_entry.grid(row=2, column=1, columnspan=2, sticky="w", padx=5, pady=5)

        # Global API Key 输入框 (始终存在，但根据选择显示/隐藏)
        self.global_key_label = ttk.Label(auth_frame, text="Global API Key:")
        self.global_key_label.grid(row=3, column=0, sticky="w", padx=5, pady=5)
        self.global_key_entry = ttk.Entry(auth_frame, textvariable=self.api_key, width=40, show="*")
        self.global_key_entry.grid(row=3, column=1, columnspan=2, sticky="w", padx=5, pady=5)

        # 规则1 IP列表
        rule1_frame = ttk.LabelFrame(self.root, text="规则1: 放行代理 IP (支持单个IP，如 1.1.1.1，或CIDR，如 1.1.1.0/24)",
                                     padding=10)
        rule1_frame.pack(fill="both", expand=True, padx=10, pady=5)

        self.ip_text = scrolledtext.ScrolledText(rule1_frame, height=6)
        self.ip_text.pack(fill="both", expand=True)

        # 规则开关
        opt_frame = ttk.Frame(self.root)
        opt_frame.pack(fill="x", padx=10, pady=5)

        ttk.Checkbutton(opt_frame, text="启用规则1: 放行代理 IP", variable=self.enable_rule1).pack(anchor="w")
        ttk.Checkbutton(opt_frame, text="启用规则2: 放行已知自动程序", variable=self.enable_rule2).pack(anchor="w")

        # 规则3配置区域
        rule3_frame = ttk.LabelFrame(self.root, text="规则3: 海外流量托管质询", padding=10)
        rule3_frame.pack(fill="x", padx=10, pady=5)

        # 规则3开关
        rule3_check_frame = ttk.Frame(rule3_frame)
        rule3_check_frame.pack(fill="x", pady=(0, 5))
        ttk.Checkbutton(rule3_check_frame, text="启用规则3: 海外流量托管质询", variable=self.enable_rule3).pack(
            side="left")

        # 默认排除的国家/地区
        default_countries_frame = ttk.LabelFrame(rule3_frame, text="默认排除的国家/地区 (建议勾选)", padding=5)
        default_countries_frame.pack(fill="x", pady=5)

        countries_frame = ttk.Frame(default_countries_frame)
        countries_frame.pack(fill="x", padx=5, pady=2)

        ttk.Checkbutton(countries_frame, text="中国大陆 (CN)", variable=self.include_cn).pack(side="left", padx=10)
        ttk.Checkbutton(countries_frame, text="香港 (HK)", variable=self.include_hk).pack(side="left", padx=10)
        ttk.Checkbutton(countries_frame, text="澳门 (MO)", variable=self.include_mo).pack(side="left", padx=10)
        ttk.Checkbutton(countries_frame, text="台湾 (TW)", variable=self.include_tw).pack(side="left", padx=10)

        # 额外指定国家/地区
        additional_countries_frame = ttk.LabelFrame(rule3_frame, text="额外指定要排除的国家/地区", padding=5)
        additional_countries_frame.pack(fill="x", pady=5)

        # 说明标签
        ttk.Label(additional_countries_frame,
                  text="使用 ISO 3166-1 alpha-2 国家代码，多个用逗号分隔 (如: US,JP,KR,GB)").pack(anchor="w", padx=5,
                                                                                                 pady=2)

        # 输入框和帮助链接
        input_help_frame = ttk.Frame(additional_countries_frame)
        input_help_frame.pack(fill="x", padx=5, pady=2)

        ttk.Entry(input_help_frame, textvariable=self.additional_countries, width=40).pack(side="left", padx=(0, 5))

        # 帮助链接
        help_link = ttk.Label(input_help_frame, text="查询国家代码", foreground="blue", cursor="hand2")
        help_link.pack(side="left")
        help_link.bind("<Button-1>", lambda e: self.open_country_codes_guide())

        # 高级选项
        advanced_frame = ttk.LabelFrame(self.root, text="高级选项", padding=10)
        advanced_frame.pack(fill="x", padx=10, pady=5)

        # 一致性检查
        ttk.Checkbutton(advanced_frame, text="启用一致性检查 (推荐)", variable=self.enable_consistency_check).pack(
            anchor="w")
        ttk.Label(advanced_frame, text="启用后将比较现有规则与要创建的规则，完全一致则跳过，避免不必要的API调用").pack(
            anchor="w", padx=20)

        # 操作模式选择
        ttk.Label(advanced_frame, text="规则操作模式:").pack(anchor="w", pady=(10, 0))
        operation_combo = ttk.Combobox(advanced_frame, textvariable=self.operation_mode,
                                       values=[m.value for m in OperationMode], state="readonly", width=25)
        operation_combo.pack(anchor="w", padx=20, pady=2)

        # 批量操作阈值
        threshold_frame = ttk.Frame(advanced_frame)
        threshold_frame.pack(fill="x", padx=20, pady=2)

        ttk.Label(threshold_frame, text="批量操作阈值:").pack(side="left")
        threshold_spinbox = ttk.Spinbox(threshold_frame, from_=5, to=100, textvariable=self.batch_threshold, width=5)
        threshold_spinbox.pack(side="left", padx=5)
        ttk.Label(threshold_frame, text="条规则").pack(side="left")

        ttk.Label(advanced_frame, text="当规则数量超过此值时，自动选择模式会切换到批量替换").pack(anchor="w", padx=20)

        # 部署按钮和日志
        btn_frame = ttk.Frame(self.root)
        btn_frame.pack(fill="x", padx=10, pady=5)

        self.deploy_btn = ttk.Button(btn_frame, text="开始部署", command=self.start_deploy)
        self.deploy_btn.pack(side="left", padx=5)

        # 日志区域
        log_frame = ttk.LabelFrame(self.root, text="部署日志", padding=5)
        log_frame.pack(fill="both", expand=True, padx=10, pady=5)

        self.log_text = scrolledtext.ScrolledText(log_frame, height=15)
        self.log_text.pack(fill="both", expand=True, padx=5, pady=5)

    def on_auth_method_change(self, *args):
        """切换认证方式时更新UI显示"""
        method = self.auth_method.get()
        if method == AuthMethod.API_TOKEN.value:
            # 显示 API Token 相关控件
            self.email_label.grid_remove()
            self.email_entry.grid_remove()
            self.token_label.grid()
            self.token_entry.grid()
            self.global_key_label.grid_remove()
            self.global_key_entry.grid_remove()
        else:
            # 显示 Global API Key 相关控件
            self.email_label.grid()
            self.email_entry.grid()
            self.token_label.grid_remove()
            self.token_entry.grid_remove()
            self.global_key_label.grid()
            self.global_key_entry.grid()

    def log(self, msg):
        """添加带时间戳的日志信息"""
        timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        formatted_msg = f"[{timestamp}] {msg}"
        self.log_text.insert(tk.END, formatted_msg + "\n")
        self.log_text.see(tk.END)
        self.root.update_idletasks()

    def api_request_with_retry(self, method, url, headers, json=None, max_retries=3):
        """带重试机制的API请求函数"""
        for attempt in range(max_retries):
            try:
                if method.upper() == 'GET':
                    resp = requests.get(url, headers=headers, timeout=30)
                elif method.upper() == 'POST':
                    resp = requests.post(url, headers=headers, json=json, timeout=30)
                elif method.upper() == 'DELETE':
                    resp = requests.delete(url, headers=headers, timeout=30)
                elif method.upper() == 'PUT':
                    resp = requests.put(url, headers=headers, json=json, timeout=30)
                else:
                    raise ValueError(f"Unsupported method: {method}")

                if resp.status_code == 429:  # Too Many Requests
                    wait_time = (2 ** attempt) + 1
                    self.log(f"  请求频繁，等待 {wait_time} 秒后重试 (尝试 {attempt + 1}/{max_retries})...")
                    time.sleep(wait_time)
                    continue

                if resp.status_code >= 500:  # 服务器错误
                    wait_time = (2 ** attempt) + 1
                    self.log(
                        f"  服务器错误 {resp.status_code}，等待 {wait_time} 秒后重试 (尝试 {attempt + 1}/{max_retries})...")
                    time.sleep(wait_time)
                    continue

                return resp

            except (requests.exceptions.Timeout, requests.exceptions.ConnectionError) as e:
                wait_time = (2 ** attempt) + 1
                self.log(f"  网络错误: {e}，等待 {wait_time} 秒后重试 (尝试 {attempt + 1}/{max_retries})...")
                time.sleep(wait_time)
                if attempt == max_retries - 1:
                    raise

        return None  # 所有重试都失败

    def start_deploy(self):
        """开始部署"""
        method = self.auth_method.get()

        if method == AuthMethod.GLOBAL_KEY.value:
            email = self.email.get().strip()
            api_key = self.api_key.get().strip()
            if not email or not api_key:
                messagebox.showerror("错误", "请输入邮箱和 Global API Key")
                return
            headers = {
                "X-Auth-Email": email,
                "X-Auth-Key": api_key,
                "Content-Type": "application/json"
            }
        else:  # API Token
            api_token = self.api_token.get().strip()
            if not api_token:
                messagebox.showerror("错误", "请输入 API Token")
                return
            headers = {
                "Authorization": f"Bearer {api_token}",
                "Content-Type": "application/json"
            }

        ip_list = self.ip_text.get("1.0", tk.END).strip().splitlines()
        ip_list = [ip.strip() for ip in ip_list if ip.strip()]

        if self.enable_rule1.get() and not ip_list:
            messagebox.showerror("错误", "启用规则1但未填写任何IP")
            return

        self.deploy_btn.config(state="disabled")
        self.log_text.delete("1.0", tk.END)
        self.log("开始部署...")
        self.log(f"认证方式: {method}")
        self.log(f"一致性检查: {'启用' if self.enable_consistency_check.get() else '禁用'}")
        self.log(f"操作模式: {self.operation_mode.get()}")
        if self.operation_mode.get() == OperationMode.AUTO.value:
            self.log(f"批量操作阈值: {self.batch_threshold.get()} 条规则")

        thread = threading.Thread(target=self.deploy, args=(headers, ip_list))
        thread.daemon = True
        thread.start()

    def deploy(self, headers, ip_list):
        """主要的部署逻辑"""
        self.log("获取域名列表...")
        zones = self.get_zones(headers)
        if zones is None:
            self.log("获取域名列表失败，请检查认证信息")
            self.deploy_btn.config(state="normal")
            return
        if not zones:
            self.log("未找到任何域名")
            self.deploy_btn.config(state="normal")
            return
        self.log(f"找到 {len(zones)} 个域名: {', '.join(z['name'] for z in zones)}")

        success_zones = 0
        skipped_zones = 0
        failed_zones = []

        for zone in zones:
            zone_id = zone["id"]
            zone_name = zone["name"]
            self.log(f"\n=== 处理域名: {zone_name} ===")

            # 构建要创建的规则
            rules = self.build_rules(ip_list)
            if not rules:
                self.log("  没有规则需要创建，跳过")
                skipped_zones += 1
                continue

            # 获取或创建规则集
            ruleset_id = self.get_or_create_ruleset(headers, zone_id)
            if not ruleset_id:
                self.log(f"  无法获取或创建规则集，跳过")
                failed_zones.append(zone_name)
                continue

            # 如果启用了一致性检查，先检查规则是否完全一致
            if self.enable_consistency_check.get():
                self.log("  检查规则一致性...")
                if self.are_rules_consistent(headers, zone_id, ruleset_id, rules):
                    self.log(f"  ✓ 规则完全一致，跳过此域名")
                    skipped_zones += 1
                    continue
                else:
                    self.log("  ✗ 规则不一致，需要更新")

            # 获取现有规则列表（用于回退保护）
            existing_rules = self.get_existing_rules(headers, zone_id, ruleset_id)
            if existing_rules is None:
                self.log("  无法获取现有规则，跳过")
                failed_zones.append(zone_name)
                continue

            # 根据操作模式决定如何删除现有规则
            operation_mode = self.operation_mode.get()
            num_existing_rules = len(existing_rules)

            # 如果是自动模式，根据规则数量决定使用哪种方式
            if operation_mode == OperationMode.AUTO.value:
                if num_existing_rules >= self.batch_threshold.get():
                    self.log(
                        f"  现有规则 {num_existing_rules} 条，超过阈值 {self.batch_threshold.get()}，使用批量替换模式")
                    operation_mode = OperationMode.BATCH_REPLACE.value
                else:
                    self.log(f"  现有规则 {num_existing_rules} 条，使用逐条删除模式")
                    operation_mode = OperationMode.DELETE_ONE_BY_ONE.value

            if operation_mode == OperationMode.BATCH_REPLACE.value:
                # 使用批量替换模式
                self.log("  批量替换现有规则...")
                if not self.replace_rules_with_empty(headers, zone_id, ruleset_id, existing_rules):
                    self.log("  批量替换失败，尝试恢复...")
                    self.restore_rules(headers, zone_id, ruleset_id, existing_rules)
                    failed_zones.append(zone_name)
                    continue
            else:
                # 使用逐条删除模式
                self.log("  逐条删除现有规则...")
                if not self.delete_all_custom_rules(headers, zone_id, ruleset_id, existing_rules):
                    self.log("  删除规则失败，尝试恢复...")
                    self.restore_rules(headers, zone_id, ruleset_id, existing_rules)
                    failed_zones.append(zone_name)
                    continue

            # 创建新规则
            self.log("  创建新规则...")
            zone_success = True
            created_rules = []

            for rule in rules:
                success = self.create_rule(headers, zone_id, ruleset_id, rule)
                if success:
                    created_rules.append(rule)
                    self.log(f"    ✓ 成功创建规则: {rule['description']}")
                else:
                    self.log(f"    ✗ 创建规则失败: {rule['description']}")
                    zone_success = False

            if zone_success:
                success_zones += 1
                self.log(f"  ✓ 域名 {zone_name} 处理完成")
            else:
                # 创建失败，尝试恢复原规则
                self.log(f"  ✗ 创建规则失败，尝试恢复原有规则...")
                self.restore_rules(headers, zone_id, ruleset_id, existing_rules)
                failed_zones.append(zone_name)

        # 部署结果摘要
        self.log("\n" + "=" * 50)
        self.log("部署完成！")
        self.log(f"成功处理: {success_zones} 个域名")
        self.log(f"跳过处理: {skipped_zones} 个域名 (规则完全一致)")
        self.log(f"失败处理: {len(failed_zones)} 个域名")
        if failed_zones:
            self.log(f"失败的域名: {', '.join(failed_zones)}")
        self.log("=" * 50)
        self.deploy_btn.config(state="normal")

    def build_rules(self, ip_list):
        """构建要创建的规则列表"""
        rules = []

        # 规则1 - 优化表达式构建
        if self.enable_rule1.get():
            # 分离单个IP和CIDR
            single_ips = []
            cidr_ranges = []

            for ip in ip_list:
                ip = ip.strip()
                if not ip:
                    continue

                if '/' in ip:
                    cidr_ranges.append(ip)
                else:
                    single_ips.append(ip)

            expr_parts = []

            # 处理CIDR范围
            for cidr in cidr_ranges:
                expr_parts.append(f'(ip.src in {cidr})')

            # 处理单个IP地址
            if single_ips:
                if len(single_ips) == 1:
                    # 单个IP使用 eq 操作符
                    expr_parts.append(f'(ip.src eq {single_ips[0]})')
                else:
                    # 多个IP使用 in 操作符加列表
                    ip_list_str = " ".join(single_ips)
                    expr_parts.append(f'(ip.src in {{{ip_list_str}}})')

            if expr_parts:
                if len(expr_parts) == 1:
                    expr1 = expr_parts[0]
                else:
                    expr1 = " or ".join(expr_parts)

                # 检查表达式长度
                expr_length = len(expr1)
                if expr_length > 3500:
                    self.log(f"  ⚠️ 警告：规则表达式长度 {expr_length} 字符，可能超过 Cloudflare 限制")
                elif expr_length > 2000:
                    self.log(f"  ℹ️ 提示：规则表达式长度 {expr_length} 字符")

                rules.append({
                    "description": "Allow My VPS",
                    "expression": expr1,
                    "action": "skip",
                    "action_parameters": {
                        "ruleset": "current",
                        "phases": [
                            "http_ratelimit",
                            "http_request_firewall_managed",
                            "http_request_sbfm"
                        ],
                        "products": [
                            "zoneLockdown",
                            "uaBlock",
                            "bic",
                            "hot",
                            "securityLevel",
                            "rateLimit",
                            "waf"
                        ]
                    }
                })

        # 规则2
        if self.enable_rule2.get():
            rules.append({
                "description": "Allow Known Bots",
                "expression": "cf.client.bot",
                "action": "skip",
                "action_parameters": {
                    "ruleset": "current",
                    "phases": ["http_ratelimit"]
                }
            })

        # 规则3
        if self.enable_rule3.get():
            # 构建排除的国家/地区列表
            excluded_countries = []

            if self.include_cn.get():
                excluded_countries.append('"CN"')
            if self.include_hk.get():
                excluded_countries.append('"HK"')
            if self.include_mo.get():
                excluded_countries.append('"MO"')
            if self.include_tw.get():
                excluded_countries.append('"TW"')

            # 处理额外指定的国家/地区
            additional_countries = self.additional_countries.get().strip()
            if additional_countries:
                countries = [country.strip().upper() for country in additional_countries.split(',') if country.strip()]
                for country in countries:
                    excluded_countries.append(f'"{country}"')

            if excluded_countries:
                # 构建表达式：排除指定国家/地区
                expr_parts = [f'(ip.src.country ne {country})' for country in excluded_countries]
                expression = " and ".join(expr_parts)

                # 检查表达式长度
                if len(expression) > 3500:
                    self.log(f"  ⚠️ 警告：规则3表达式长度 {len(expression)} 字符，可能超过 Cloudflare 限制")

                rules.append({
                    "description": "Overseas Managed Challenge",
                    "expression": expression,
                    "action": "managed_challenge"
                })

        return rules

    def get_existing_rules(self, headers, zone_id, ruleset_id):
        """获取现有规则列表"""
        url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{ruleset_id}"
        try:
            resp = self.api_request_with_retry('GET', url, headers)
            if resp is None or resp.status_code != 200:
                return None

            data = resp.json()
            if not data.get("success", False):
                return None

            ruleset = data.get("result", {})
            rules = ruleset.get("rules", [])

            # 清理规则对象，移除不需要的字段
            cleaned_rules = []
            for rule in rules:
                cleaned_rule = {
                    "description": rule.get("description", ""),
                    "expression": rule.get("expression", ""),
                    "action": rule.get("action", ""),
                }
                if "action_parameters" in rule:
                    cleaned_rule["action_parameters"] = rule["action_parameters"]
                cleaned_rules.append(cleaned_rule)

            return cleaned_rules
        except Exception as e:
            self.log(f"  获取现有规则异常: {e}")
            return None

    def are_rules_consistent(self, headers, zone_id, ruleset_id, new_rules):
        """比较现有规则与要创建的规则是否完全一致"""
        existing_rules = self.get_existing_rules(headers, zone_id, ruleset_id)
        if existing_rules is None:
            return False

        # 规则数量不一致
        if len(existing_rules) != len(new_rules):
            return False

        # 逐条比较规则
        for existing_rule, new_rule in zip(existing_rules, new_rules):
            # 比较规则关键属性
            if (existing_rule.get("description") != new_rule.get("description") or
                    existing_rule.get("expression") != new_rule.get("expression") or
                    existing_rule.get("action") != new_rule.get("action")):
                return False

            # 比较动作参数
            existing_params = existing_rule.get("action_parameters", {})
            new_params = new_rule.get("action_parameters", {})

            # 深度比较字典
            if not self.compare_dicts(existing_params, new_params):
                return False

        return True

    def compare_dicts(self, dict1, dict2):
        """深度比较两个字典是否相等"""
        if dict1 is None and dict2 is None:
            return True
        if dict1 is None or dict2 is None:
            return False

        # 序列化为JSON字符串进行比较，避免嵌套结构比较的复杂性
        try:
            return json.dumps(dict1, sort_keys=True) == json.dumps(dict2, sort_keys=True)
        except:
            return dict1 == dict2

    def restore_rules(self, headers, zone_id, ruleset_id, rules):
        """恢复原有规则"""
        if not rules:
            return False

        self.log(f"  正在恢复 {len(rules)} 条规则...")
        success_count = 0

        for rule in rules:
            success = self.create_rule(headers, zone_id, ruleset_id, rule)
            if success:
                success_count += 1
            else:
                self.log(f"    ✗ 恢复规则失败: {rule.get('description', '未知规则')}")

        if success_count == len(rules):
            self.log(f"  ✓ 成功恢复所有规则")
            return True
        else:
            self.log(f"  ✗ 只恢复了 {success_count}/{len(rules)} 条规则")
            return False

    def replace_rules_with_empty(self, headers, zone_id, ruleset_id, existing_rules):
        """使用PUT请求替换整个规则集（清空现有规则）"""
        url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{ruleset_id}"

        try:
            # 首先获取规则集的完整信息
            resp = self.api_request_with_retry('GET', url, headers)
            if resp is None or resp.status_code != 200:
                return False

            ruleset = resp.json().get("result", {})

            # 准备PUT请求的数据，只清空rules数组，保留其他元信息
            payload = {
                "name": ruleset.get("name", "default"),
                "kind": ruleset.get("kind", "zone"),
                "phase": ruleset.get("phase", "http_request_firewall_custom"),
                "description": ruleset.get("description", "Custom WAF Rules"),
                "rules": []  # 清空规则
            }

            # 发送PUT请求
            resp = self.api_request_with_retry('PUT', url, headers, json=payload)
            if resp is None or resp.status_code != 200:
                self.log(f"    批量替换失败，状态码: {resp.status_code if resp else '无响应'}")
                return False

            return True
        except Exception as e:
            self.log(f"  批量替换异常: {e}")
            return False

    # ----- API 辅助方法（已集成重试机制）-----
    def get_zones(self, headers):
        """获取所有域名（已包含分页逻辑，无数量限制）"""
        zones = []
        page = 1
        while True:
            url = f"https://api.cloudflare.com/client/v4/zones?page={page}&per_page=50"
            try:
                resp = self.api_request_with_retry('GET', url, headers)
                if resp is None:
                    self.log("获取域名列表失败：达到最大重试次数")
                    return None

                if resp.status_code != 200:
                    self.log(f"获取域名列表失败，状态码: {resp.status_code}")
                    return None

                data = resp.json()
                if not data.get("success"):
                    self.log(f"API 返回失败: {data.get('errors', [{}])[0].get('message', 'Unknown error')}")
                    return None

                for zone in data.get("result", []):
                    zones.append({"id": zone["id"], "name": zone["name"]})

                if not data.get("result_info", {}).get("has_more", False):
                    break

                page += 1
            except Exception as e:
                self.log(f"获取域名列表异常: {e}")
                return None
        return zones

    def get_or_create_ruleset(self, headers, zone_id):
        """获取或创建自定义规则集"""
        url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets"
        try:
            resp = self.api_request_with_retry('GET', url, headers)
            if resp is None:
                return None

            if resp.status_code == 200:
                data = resp.json()
                for ruleset in data.get("result", []):
                    if ruleset.get("kind") == "zone" and ruleset.get("phase") == "http_request_firewall_custom":
                        return ruleset["id"]

            # 创建新的规则集
            create_url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets"
            payload = {
                "name": "default",
                "kind": "zone",
                "phase": "http_request_firewall_custom",
                "description": "Custom WAF Rules"
            }
            resp = self.api_request_with_retry('POST', create_url, headers, json=payload)
            if resp is None:
                return None

            if resp.status_code == 200:
                data = resp.json()
                return data["result"]["id"]
            else:
                self.log(f"创建规则集失败，状态码: {resp.status_code}")
                return None
        except Exception as e:
            self.log(f"获取/创建规则集异常: {e}")
            return None

    def delete_all_custom_rules(self, headers, zone_id, ruleset_id, existing_rules=None):
        """逐条删除规则集中的所有自定义规则"""
        url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{ruleset_id}"
        try:
            resp = self.api_request_with_retry('GET', url, headers)
            if resp is None or resp.status_code != 200:
                return False

            ruleset = resp.json().get("result", {})
            rules = ruleset.get("rules", [])

            self.log(f"    需要删除 {len(rules)} 条规则")
            success = True
            delete_count = 0

            for rule in rules:
                rule_id = rule["id"]
                rule_desc = rule.get("description", f"规则{delete_count + 1}")
                delete_url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{ruleset_id}/rules/{rule_id}"
                del_resp = self.api_request_with_retry('DELETE', delete_url, headers)
                if del_resp is None or del_resp.status_code not in [200, 204]:
                    success = False
                    self.log(f"      ✗ 删除规则失败: {rule_desc}")
                else:
                    delete_count += 1

            if success:
                self.log(f"    ✓ 成功删除 {delete_count} 条规则")
            else:
                self.log(f"    ✗ 部分规则删除失败，成功删除 {delete_count}/{len(rules)} 条规则")

            return success
        except Exception as e:
            self.log(f"  删除规则异常: {e}")
            return False

    def create_rule(self, headers, zone_id, ruleset_id, rule):
        """创建单条规则"""
        url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{ruleset_id}/rules"
        payload = {
            "description": rule["description"],
            "expression": rule["expression"],
            "action": rule["action"]
        }
        if "action_parameters" in rule:
            payload["action_parameters"] = rule["action_parameters"]
        try:
            resp = self.api_request_with_retry('POST', url, headers, json=payload)
            if resp is None:
                return False

            if resp.status_code == 200:
                return True
            else:
                error_msg = resp.text[:200]  # 截取前200字符避免日志过长
                self.log(f"      错误 {resp.status_code}: {error_msg}")
                return False
        except Exception as e:
            self.log(f"      创建规则异常: {e}")
            return False


if __name__ == "__main__":
    root = tk.Tk()
    app = CloudflareWAFApp(root)
    root.mainloop()
