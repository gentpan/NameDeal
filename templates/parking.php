<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <title><?php echo htmlspecialchars($title); ?></title>

    <!-- 引入外部 CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://icons.bluecdn.com/fontawesome-pro@7.2.0/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        /* 从 PHP 传递主题颜色，如果设置了就覆盖默认的 #0066FC */
        <?php if (!empty($themeColor) && $themeColor !== '#0066FC'): ?> :root {
            --primary-color: <?php echo $themeColor; ?>;
            --primary-hover: <?php echo $themeColor; ?>dd;
            --primary-light: <?php echo $themeColor; ?>20;
        }

        <?php endif; ?>
    </style>
</head>

<body>
    <!-- 背景图片层 -->
    <div class="background-image"></div>


    <div class="container">
        <div class="header-row">
            <h1>
                <span class="h1-chinese">域名，让品牌更有价值。</span>
                <span class="h1-english">Premium domains. Powerful brands.</span>
            </h1>
            <div class="logo">
                <svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg">
                    <path d="M663.04 80H156.16A92.16 92.16 0 0 0 64 172.16v506.88a92.16 92.16 0 0 0 92.16 92.16h506.88a92.16 92.16 0 0 0 92.16-92.16V172.16A92.16 92.16 0 0 0 663.04 80z" fill="<?php echo htmlspecialchars($themeColor); ?>"></path>
                    <path d="M855.04 272H348.16A92.16 92.16 0 0 0 256 364.16v506.88a92.16 92.16 0 0 0 92.16 92.16h506.88a92.16 92.16 0 0 0 92.16-92.16V364.16A92.16 92.16 0 0 0 855.04 272z" fill="<?php echo htmlspecialchars($themeColor); ?>" opacity=".4"></path>
                    <path d="M348.16 280h506.88a84.16 84.16 0 0 1 84.16 84.16v506.88a84.16 84.16 0 0 1-84.16 84.16H348.16a84.16 84.16 0 0 1-84.16-84.16V364.16a84.16 84.16 0 0 1 84.16-84.16z m607.04 84.16a100.16 100.16 0 0 0-100.16-100.16H348.16a100.16 100.16 0 0 0-100.16 100.16v506.88a100.16 100.16 0 0 0 100.16 100.16h506.88a100.16 100.16 0 0 0 100.16-100.16V364.16z" fill="<?php echo htmlspecialchars($themeColor); ?>" opacity=".2"></path>
                    <path d="M754.688 755.36H461.312a6.32 6.32 0 0 0-4.384 10.96C495.248 806.816 548.88 832 608 832c59.12 0 113.856-25.184 151.072-65.68a6.32 6.32 0 0 0-4.384-10.944zM443.792 514.544h328.416c5.472 0 8.752-6.56 5.472-10.944A208.32 208.32 0 0 0 608 416a208.32 208.32 0 0 0-169.68 87.584c-3.296 4.368 0 10.944 5.472 10.944z m328.416 32.848H443.792A43.92 43.92 0 0 0 400 591.152v87.584a43.92 43.92 0 0 0 43.792 43.792h328.416A43.92 43.92 0 0 0 816 678.72v-87.584a43.92 43.92 0 0 0-43.792-43.776z m-224.416 59.104l-16.416 72.256c-2.192 6.56-6.576 8.752-13.152 8.752-6.56 0-10.944-3.28-13.12-8.752l-15.344-50.352-15.312 50.352c-2.192 5.472-6.56 8.752-13.136 8.752s-10.96-3.28-13.152-8.752l-16.416-72.256v-4.368c1.104-5.488 3.28-8.768 8.768-8.768 5.472 0 9.84 3.28 10.944 9.856l10.944 55.84 16.416-56.928c2.192-4.384 5.472-7.68 9.856-7.68 5.472 0 8.752 2.192 9.856 7.68l16.416 56.928 10.944-55.84c1.104-6.56 4.384-9.856 9.856-9.856 5.472 0 8.768 3.28 8.768 8.768 3.28 1.088 3.28 3.28 3.28 4.368z m118.24 0L649.6 678.736c-2.192 6.56-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752L608 628.384l-15.328 50.352c-2.192 5.472-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752l-16.416-72.256v-4.368c1.088-5.488 3.28-8.768 8.752-8.768 5.472 0 9.856 3.28 10.944 9.856l10.944 55.84 16.432-56.928c2.192-4.384 5.472-7.68 9.856-7.68 5.472 0 8.752 2.192 9.84 7.68l16.432 56.928 10.944-55.84c1.088-6.56 4.384-9.856 9.856-9.856 5.472 0 8.752 3.28 8.752 8.768 4.384 1.088 4.384 3.28 3.28 4.368z m119.312 0l-16.416 72.256c-2.192 6.56-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752l-15.328-50.352-15.328 50.352c-2.192 5.472-6.56 8.752-13.136 8.752-6.56 0-10.944-3.28-13.136-8.752l-16.416-72.256v-4.368c1.088-5.488 3.28-8.768 8.752-8.768 5.472 0 9.856 3.28 10.944 9.856l10.944 55.84 16.432-56.928c2.176-4.384 5.472-7.68 9.84-7.68 5.488 0 8.768 2.192 9.856 7.68l16.432 56.928 10.944-55.84c1.088-6.56 4.368-9.856 9.856-9.856 5.472 0 8.752 3.28 8.752 8.768 4.384 1.088 3.28 3.28 3.28 4.368z" fill="#FFFFFF"></path>
                </svg>
            </div>
        </div>

        <div class="domain">
            <span class="domain-label">D<span class="domain-label-o">o</span>main</span>
            <span class="domain-name"><?php echo strtoupper(htmlspecialchars($currentDomain)); ?></span>
            <button type="button" class="home-whois-toggle" id="homeWhoisToggle" data-domain="<?php echo htmlspecialchars($currentDomain, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false" title="WHOIS 一键查询">
                <svg class="home-whois-icon" viewBox="0 0 1024 1024" aria-hidden="true" focusable="false">
                    <path d="M707.621926 350.549333l-45.037037 6.637037C636.416 179.465481 567.580444 60.681481 498.839704 60.681481c-68.077037 0-136.343704 116.508444-163.081482 291.802075l-44.980148-6.864593C320.587852 150.243556 399.701333 15.17037 498.839704 15.17037c99.972741 0 179.617185 137.462519 208.782222 335.378963zM290.664296 677.641481l44.999111-6.826666c26.661926 175.653926 95.004444 292.503704 163.176297 292.503704 68.266667 0 136.722963-117.229037 163.271111-293.281186l44.999111 6.788741C677.546667 872.997926 598.224593 1008.82963 498.839704 1008.82963c-99.252148 0-178.460444-135.433481-208.175408-331.188149z" fill="currentColor"></path>
                    <path d="M512 1008.82963C237.605926 1008.82963 15.17037 786.394074 15.17037 512 15.17037 237.605926 237.605926 15.17037 512 15.17037 786.394074 15.17037 1008.82963 237.605926 1008.82963 512c0 274.394074-222.435556 496.82963-496.82963 496.82963z m0-45.511111c249.249185 0 451.318519-202.069333 451.318519-451.318519S761.249185 60.681481 512 60.681481 60.681481 262.750815 60.681481 512 262.750815 963.318519 512 963.318519z" fill="currentColor"></path>
                    <path d="M64.265481 376.737185v-45.511111H959.715556v45.511111H64.284444zM959.715556 647.262815v45.511111H64.284444v-45.511111H959.715556z" fill="currentColor"></path>
                    <path d="M118.139259 429.131852h31.288889l31.744 128.720592h0.948148l33.431704-128.701629h28.672l33.431704 128.701629h0.948148l31.762963-128.701629h31.288889l-48.82963 169.244444h-29.392593l-32.957629-127.29837h-0.948148l-33.185185 127.29837H166.72237L118.120296 429.131852z m241.284741 0h27.742815v70.656h85.807407V429.131852h27.723852v169.244444h-27.723852v-74.903703h-85.807407v74.903703h-27.742815V429.131852z m249.609481-3.299556c25.903407 0 46.288593 8.362667 61.155556 25.125926 14.222222 15.796148 21.333333 36.807111 21.333333 63.051852 0 25.903407-7.111111 46.838519-21.333333 62.805333-14.866963 16.592593-35.252148 24.89837-61.155556 24.898371-25.92237 0-46.307556-8.38163-61.155555-25.125926-14.070519-15.966815-21.105778-36.826074-21.105778-62.577778 0-25.92237 7.035259-46.857481 21.105778-62.824296 14.52563-16.914963 34.910815-25.353481 61.155555-25.353482z m0 24.405334c-17.389037 0-30.985481 5.935407-40.77037 17.787259-9.178074 11.207111-13.748148 26.548148-13.748148 45.985185 0 19.26637 4.570074 34.512593 13.748148 45.738667 9.481481 11.700148 23.058963 17.540741 40.77037 17.54074 17.540741 0 31.04237-5.613037 40.523852-16.820148 9.329778-11.226074 13.994667-26.718815 13.994667-46.459259 0-19.759407-4.664889-35.403852-13.994667-46.933333-9.329778-11.226074-22.831407-16.839111-40.523852-16.839111z m108.562963-21.086815h27.723852v169.244444h-27.723852V429.131852z m120.642371-3.318519c20.081778 0 35.65037 4.096 46.705778 12.325926 11.851852 8.836741 18.640593 22.509037 20.385185 40.997926h-27.496297c-2.37037-10.42963-6.712889-17.938963-13.046518-22.509037-6.011259-4.589037-15.322074-6.883556-27.97037-6.883555-10.752 0-18.887111 1.517037-24.405334 4.513185-6.959407 3.470222-10.42963 9.310815-10.429629 17.54074 0 7.414519 3.944296 13.179259 11.851851 17.294223 3.792593 2.048 13.425778 5.537185 28.918519 10.429629 22.281481 6.788741 37.05363 12.325926 44.316444 16.592593 14.696296 8.685037 22.053926 20.859259 22.053926 36.503704 0 15.17037-5.935407 27.173926-17.787259 36.029629-12.003556 8.685037-28.747852 13.046519-50.251852 13.046519-20.859259 0-37.129481-4.039111-48.829629-12.098371-14.373926-9.955556-22.110815-25.675852-23.22963-47.160888h27.496296c1.896296 12.951704 6.485333 22.110815 13.748148 27.496296 6.807704 4.892444 17.066667 7.338667 30.814815 7.338666 12.325926 0 22.129778-2.048 29.392593-6.162962 7.281778-4.41837 10.903704-10.05037 10.903704-16.820149 0-9.007407-5.290667-16.118519-15.872-21.333333-3.792593-1.896296-14.791111-5.613037-32.95763-11.150222-21.010963-6.637037-33.962667-11.377778-38.874074-14.222222-12.951704-7.736889-19.437037-19.114667-19.437037-34.133334 0-15.17037 6.314667-26.927407 18.962963-35.309037 11.700148-8.229926 26.699852-12.325926 45.037037-12.325926z" fill="currentColor"></path>
                </svg>
                <span class="home-whois-spinner" aria-hidden="true"></span>
            </button>
        </div>
        <div class="home-whois-modal" id="homeWhoisModal" aria-hidden="true">
            <div class="home-whois-modal-content" role="dialog" aria-modal="true" aria-labelledby="homeWhoisTitle">
                <div class="home-whois-modal-header">
                    <h3 id="homeWhoisTitle">WHOIS 查询结果</h3>
                    <button type="button" class="home-whois-modal-close" id="homeWhoisClose" aria-label="关闭">×</button>
                </div>
                <div class="home-whois-modal-body">
                    <div class="home-whois-content" id="homeWhoisContent"></div>
                </div>
            </div>
        </div>

        <div class="description">
            <?php
            // 优先显示域名介绍，如果没有则显示默认描述
            $displayText = !empty($domainIntro) ? $domainIntro : $description;
            echo htmlspecialchars($displayText);
            ?>
        </div>

        <!-- 域名价值宣传 -->
        <div class="domain-value">
            <div class="value-content">
                <h3 class="value-title">一个好域名，一份好事业</h3>
                <p class="value-text">域名是互联网世界的唯一标识，是品牌在线形象的重要组成部分。一个好的域名能够：</p>
                <ul class="value-list">
                    <li>
                        <div class="value-item-icon">
                            <svg class="list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                <path d="M2 17l10 5 10-5"></path>
                                <path d="M2 12l10 5 10-5"></path>
                            </svg>
                        </div>
                        <div class="value-item-content">
                            <strong>提升品牌价值</strong>
                            <span class="value-item-desc">简短易记的域名让用户更容易找到您</span>
                        </div>
                    </li>
                    <li>
                        <div class="value-item-icon">
                            <svg class="list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="2" x2="12" y2="22"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <div class="value-item-content">
                            <strong>助力业务增长</strong>
                            <span class="value-item-desc">专业域名增强用户信任度</span>
                        </div>
                    </li>
                    <li>
                        <div class="value-item-icon">
                            <svg class="list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <div class="value-item-content">
                            <strong>投资潜力无限</strong>
                            <span class="value-item-desc">优质域名具有巨大升值空间</span>
                        </div>
                    </li>
                    <li>
                        <div class="value-item-icon">
                            <svg class="list-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="9" y1="9" x2="15" y2="9"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <div class="value-item-content">
                            <strong>塑造企业形象</strong>
                            <span class="value-item-desc">域名是您在线业务的第一印象</span>
                        </div>
                    </li>
                </ul>
                <p class="value-slogan">把握机会，拥有这个优质域名，开启您的成功之路！</p>
            </div>
        </div>

        <!-- 价格显示和出价区域 -->
        <div class="price-section">
            <div class="price-display">
                <div class="price-label">您的出价</div>

                <?php if (!empty($domainPrice) && is_numeric($domainPrice) && floatval($domainPrice) > 0): ?>
                    <div class="price-reference">
                        <span class="price-reference-label">参考价格：</span>
                        <span class="price-reference-value">¥<?php echo number_format(floatval($domainPrice), 2); ?></span>
                    </div>
                <?php endif; ?>

                <!-- 价格输入和验证区域（并排显示） -->
                <div class="price-input-row">
                    <div class="price-input-wrapper">
                        <span class="currency-symbol-inner">¥</span>
                        <input type="number" id="offerPrice" class="price-input" placeholder="请输入您的出价" min="<?php echo !empty($domainPrice) && is_numeric($domainPrice) ? floatval($domainPrice) : 0; ?>" step="0.01" data-min-price="<?php echo !empty($domainPrice) && is_numeric($domainPrice) ? floatval($domainPrice) : 0; ?>">
                    </div>

                    <!-- 人类验证（输入价格后显示在右侧，确认后隐藏） -->
                    <div class="price-verification-wrapper">
                        <div class="cf-verification" id="cfVerification">
                            <input type="checkbox" id="cfCheckbox">
                            <label for="cfCheckbox" class="cf-checkbox-label">
                                <div class="cf-checkbox">
                                    <svg class="cf-checkmark" viewBox="0 0 20 20">
                                        <path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill="currentColor"></path>
                                    </svg>
                                </div>
                                <span class="cf-label-text">验证您是人类</span>
                            </label>
                            <div class="cf-security-badge">
                                <div class="cf-button-text-wrapper">
                                    <span class="cf-button-text">NAMEDEAL</span>
                                    <span class="cf-button-text">SECURITY</span>
                                </div>
                                <div class="cf-verifying">
                                    <div class="cf-spinner"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="price-actions">
                    <button type="button" id="confirmPriceBtn" class="btn-confirm-price" disabled>
                        <span class="text">确认出价</span>
                        <span class="icon">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </button>
                    <button type="button" id="editPriceBtn" class="btn-edit-price" style="display: none;">
                        <span class="text">修改价格</span>
                        <span class="icon">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                            </svg>
                        </span>
                    </button>
                </div>

                <div class="price-status" id="priceStatus"></div>
            </div>
        </div>

        <div class="contact-form">
            <h2>购买咨询</h2>

            <form id="contactForm">
                <div class="form-row-two">
                    <div class="form-group">
                        <label for="name">您的姓名 <span style="color: red;">*</span></label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <div class="label-with-hint">
                            <label for="email">您的邮箱 <span style="color: red;">*</span></label>
                            <span class="email-hint">系统将发送验证码进行身份验证</span>
                        </div>
                        <div class="email-verify-wrapper">
                            <input type="email" id="email" name="email" required>
                            <button type="button" class="btn-verify-email" id="verifyEmailBtn" style="display: none;">
                                <span class="text">验证</span>
                                <span class="icon">
                                    <svg viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="message">留言内容 <span style="color: red;">*</span></label>
                    <textarea id="message" name="message" required></textarea>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <span class="text">提交购买咨询</span>
                    <span class="icon">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </button>

                <div class="message" id="messageBox"></div>
            </form>
        </div>

        <div class="footer">
            <?php
            // 获取本月访问统计
            // 使用 HTTP_HOST 确保与统计记录时的域名格式一致（包含端口号）
            $statsDomain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $currentDomain;
            require_once __DIR__ . '/../core/StatsTracker.php';
            $statsTracker = new StatsTracker();
            $monthStats = $statsTracker->getStats($statsDomain, 30);
            $monthVisits = isset($monthStats['total_visits']) ? $monthStats['total_visits'] : 0;
            ?>

            <div class="footer-content">
                <div class="footer-copyright">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo !empty($siteName) ? htmlspecialchars($siteName) : 'NameDeal'; ?></p>
                    <p class="footer-copyright-encoded" data-copyright="<?php echo base64_encode('Powered by 西风 - Copyright protection - github.com/gentpan/namedeal'); ?>" style="display: none;"></p>
                </div>

                <div class="footer-stats">
                    <span class="stats-item">
                        <svg width="16" height="16" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
                            <path d="M64.67 512c2.03-148.27 27.78-271.04 103.07-344.26C240.96 92.45 363.73 66.7 512 64.67c148.27 2.03 271.04 27.78 344.26 103.07C931.55 240.96 957.3 363.73 959.33 512c-2.03 148.27-27.78 271.04-103.07 344.26C783.04 931.55 660.27 957.3 512 959.33c-148.27-2.03-271.04-27.78-344.26-103.07C92.45 783.04 66.7 660.27 64.67 512z" fill="currentColor" opacity="0.2" />
                            <path d="M339.12 720.13c-26.83 0-48.77-21.95-48.77-48.77V446.89c0-26.83 21.95-48.77 48.77-48.77 26.83 0 48.77 21.95 48.77 48.77v224.47c0.01 26.82-21.94 48.77-48.77 48.77zM512 720.13c-26.83 0-48.77-21.95-48.77-48.77V352.64c0-26.83 21.95-48.77 48.77-48.77 26.83 0 48.77 21.95 48.77 48.77v318.71c0 26.83-21.94 48.78-48.77 48.78zM684.88 720.13c-26.83 0-48.77-21.95-48.77-48.77V533.13c0-26.83 21.95-48.77 48.77-48.77 26.83 0 48.77 21.95 48.77 48.77v138.23c0 26.82-21.95 48.77-48.77 48.77z" fill="currentColor" />
                        </svg>
                        <span class="stats-text">本月访问: <?php echo number_format($monthVisits); ?> 次</span>
                    </span>
                </div>

                <div class="footer-links">
                    <?php
                    $encodedDomain = rawurlencode((string) $currentDomain);
                    $normalizeFooterSvgIcon = static function ($svgCode) {
                        $svg = trim((string)$svgCode);
                        if ($svg === '') {
                            return '';
                        }

                        // 移除内联 style，避免覆盖链接 hover 颜色
                        $svg = preg_replace('/\sstyle\s*=\s*(["\']).*?\1/si', '', $svg);

                        // 统一 fill/stroke 为 currentColor，保留 none
                        $svg = preg_replace_callback('/\sfill\s*=\s*(["\'])(.*?)\1/si', static function ($m) {
                            $value = strtolower(trim((string)$m[2]));
                            return $value === 'none' ? ' fill="none"' : ' fill="currentColor"';
                        }, $svg);

                        $svg = preg_replace_callback('/\sstroke\s*=\s*(["\'])(.*?)\1/si', static function ($m) {
                            $value = strtolower(trim((string)$m[2]));
                            return $value === 'none' ? ' stroke="none"' : ' stroke="currentColor"';
                        }, $svg);

                        return $svg;
                    };

                    $defaultFooterLinks = [
                        ['name' => 'WHOIS查询', 'url' => $footerWhoisUrl ?? 'https://bluewhois.com/{domain}', 'icon_class' => 'fa-solid fa-magnifying-glass'],
                        ['name' => '西风', 'url' => $footerXifengUrl ?? 'https://xifeng.net', 'icon_class' => 'fa-solid fa-wind'],
                        ['name' => '更多域名', 'url' => $footerMoreDomainsUrl ?? 'https://domain.ls', 'icon_class' => 'fa-solid fa-globe'],
                    ];
                    $renderFooterLinks = (isset($footerLinks) && is_array($footerLinks) && !empty($footerLinks)) ? array_slice($footerLinks, 0, 3) : $defaultFooterLinks;

                    foreach ($renderFooterLinks as $item):
                        $name = trim((string)($item['name'] ?? ''));
                        $urlTemplate = trim((string)($item['url'] ?? ''));
                        $iconClass = trim((string)($item['icon_class'] ?? 'fa-solid fa-link'));

                        if ($name === '' || $urlTemplate === '') {
                            continue;
                        }

                        $href = strpos($urlTemplate, '{domain}') !== false
                            ? str_replace('{domain}', $encodedDomain, $urlTemplate)
                            : $urlTemplate;
                        if (!filter_var($href, FILTER_VALIDATE_URL)) {
                            continue;
                        }

                        // 判断图标类型：SVG 代码 或 FontAwesome 类名
                        $isSvg = (stripos(trim($iconClass), '<svg') === 0 && stripos($iconClass, '</svg>') !== false);
                        $isFa = preg_match('/^(fa-(solid|regular|brands|duotone|thin|light)\s+)?fa-[a-z0-9-]+(?:\s+fa-[a-z0-9-]+)*$/i', $iconClass);
                        if (!$isSvg && !$isFa) {
                            $iconClass = 'fa-solid fa-link';
                            $isFa = true;
                            $isSvg = false;
                        }
                        // SVG 安全检查
                        if ($isSvg && preg_match('/<script|on\w+\s*=|javascript:/i', strtolower($iconClass))) {
                            $iconClass = 'fa-solid fa-link';
                            $isFa = true;
                            $isSvg = false;
                        }
                        if ($isSvg) {
                            $iconClass = $normalizeFooterSvgIcon($iconClass);
                        }
                    ?>
                        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" title="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($isSvg): ?>
                                <span class="footer-icon footer-icon-svg" aria-hidden="true"><?php echo $iconClass; ?></span>
                            <?php else: ?>
                                <i class="footer-icon <?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="https://github.com/gentpan/namedeal" target="_blank" rel="noopener" title="GitHub">
                        <i class="footer-icon fa-brands fa-github" aria-hidden="true"></i>
                        GitHub
                    </a>
                </div>

                <div class="theme-toggle-wrapper">
                    <div class="theme-toggle" title="主题" id="themeToggle">
                        <svg class="current-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                            <!-- 图标会根据当前模式动态切换 -->
                        </svg>
                        <span class="theme-toggle-text">主题</span>
                    </div>
                    <div class="theme-menu" id="themeMenu">
                        <div class="theme-menu-item" data-theme-mode="light">
                            <svg class="theme-menu-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z" />
                            </svg>
                            <span class="theme-menu-text">浅色模式</span>
                            <svg class="theme-menu-check" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                        </div>
                        <div class="theme-menu-separator"></div>
                        <div class="theme-menu-item" data-theme-mode="dark">
                            <svg class="theme-menu-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd" />
                            </svg>
                            <span class="theme-menu-text">深色模式</span>
                            <svg class="theme-menu-check" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                        </div>
                        <div class="theme-menu-separator"></div>
                        <div class="theme-menu-item" data-theme-mode="auto">
                            <svg class="theme-menu-icon" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
                                <path d="M20 3H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h4v2H8v2h8v-2h-4v-2h4c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 12H4V5h16v10z" />
                            </svg>
                            <span class="theme-menu-text">跟随系统</span>
                            <svg class="theme-menu-check" viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display: none;">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 验证码输入弹窗 -->
    <div class="otp-modal" id="otpModal" style="display: none;">
        <div class="otp-Form">
            <button class="exitBtn" id="closeOtpModal" type="button">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
                </svg>
            </button>
            <h2 class="mainHeading">验证码验证</h2>
            <p class="otpSubheading">请输入发送到您邮箱的4位验证码</p>
            <button type="button" class="btn-send-code" id="sendCodeBtn" style="display: none; width: 100%; margin-bottom: 20px;">
                <span class="text">发送验证码</span>
                <span class="icon">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                    </svg>
                </span>
            </button>
            <div class="inputContainer" id="inputContainer" style="display: none;">
                <input type="text" class="otp-input" id="otpInput1" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" class="otp-input" id="otpInput2" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" class="otp-input" id="otpInput3" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input type="text" class="otp-input" id="otpInput4" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
            </div>
            <button type="button" class="verifyButton" id="verifyButton" style="display: none;">验证</button>
            <div class="resendNote" id="resendNote" style="display: none;">
                <span id="otpTimer">验证码有效期：剩余 5分钟</span>
                <button type="button" class="resendBtn" id="resendBtn" disabled>重新发送</button>
            </div>
        </div>
    </div>

    <!-- 引入外部 JavaScript -->
    <script src="assets/js/main.js"></script>
    <?php if (!empty($footerAnalyticsCode)): ?>
        <?php echo $footerAnalyticsCode; ?>
    <?php endif; ?>
</body>

</html>
