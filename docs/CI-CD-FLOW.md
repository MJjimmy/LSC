# LSC — GitHub CI and Railway delivery flow

This diagram describes **LSC's existing flow**. It is documentation, not a new workflow or a deployment configuration change.

- **Android source:** `MJjimmy/LSC-APP`, branch `master`.
- **Backend source:** `MJjimmy/LSC`, deployment branch `deploy/railpack`.
- **CI:** GitHub Actions checks source and publishes the Android build/test artifacts.
- **Delivery:** Railway deploys the PHP web service separately; that service connects to MySQL.
- **Manual acceptance:** installing a compatible APK and testing the app on a physical phone remain separate tasks.

> **Important:** A Railway deployment gate that blocks on GitHub CI results has not been verified. The deployment arrow therefore starts from source/release action, **not** from the tests-passed node. A green workflow also does not mean every job ran; inspect skipped jobs and the exact source revision.

## GitHub-native Mermaid diagram

```mermaid
flowchart LR
    DEV["Code change<br/>Commit and push"]

    subgraph REPOS["GitHub source repositories"]
        direction TB
        ANDROID_REPO["MJjimmy/LSC-APP<br/>master"]
        BACKEND_REPO["MJjimmy/LSC<br/>deploy/railpack"]
    end

    subgraph ACTIONS["Continuous integration — GitHub Actions"]
        direction TB
        ANDROID_CI["Android checkout and tool verification<br/>JVM tests + debug APK build"]
        ARTIFACTS["Publish debug APK<br/>and JUnit / HTML reports"]
        BACKEND_CI["Administrator / authentication regressions<br/>PHP / provider checks offline<br/>Security / cache checks when triggered"]
        RESULTS["Inspect exact-commit run results<br/>Passed / failed / skipped jobs"]
        FIX["Failed check?<br/>Fix source and push another commit"]
    end

    PHONE["Manual phone acceptance<br/>Install compatible APK and test features"]

    subgraph RAILWAY["Production delivery — separate Railway path"]
        direction LR
        BUILD["Configured source / release action<br/>Railway build and deployment"]
        WEB["Live PHP web service<br/>lsc.up.railway.app"]
        MYSQL[("MySQL<br/>Private database and existing volume")]
    end

    DEV --> ANDROID_REPO
    DEV --> BACKEND_REPO
    ANDROID_REPO -->|Matching trigger| ANDROID_CI
    ANDROID_CI -->|Success| ARTIFACTS
    ARTIFACTS -->|Manual installation| PHONE
    BACKEND_REPO -->|Matching trigger| BACKEND_CI
    BACKEND_CI --> RESULTS
    ANDROID_CI -. Failure .-> FIX
    BACKEND_CI -. Failure .-> FIX
    BACKEND_REPO -. Separate source / release action .-> BUILD
    BUILD --> WEB
    WEB -->|Private SQL connection| MYSQL

    classDef source fill:#1e293b,stroke:#94a3b8,color:#f8fafc;
    classDef ci fill:#064e3b,stroke:#34d399,color:#ecfdf5;
    classDef manual fill:#083344,stroke:#22d3ee,color:#ecfeff;
    classDef cloud fill:#422d12,stroke:#fbbf24,color:#fffbeb;
    classDef database fill:#31204f,stroke:#a78bfa,color:#f5f3ff;
    classDef feedback fill:#4c1d30,stroke:#fb7185,color:#fff1f2;

    class DEV,ANDROID_REPO,BACKEND_REPO source;
    class ANDROID_CI,ARTIFACTS,BACKEND_CI,RESULTS ci;
    class PHONE manual;
    class BUILD,WEB cloud;
    class MYSQL database;
    class FIX feedback;
```

## Presentation-ready version

The image below is the same flow in a 16:9 presentation layout. The Mermaid block above is editable directly in GitHub; the SVG is also provided for vector editing.

![LSC GitHub CI and separate Railway delivery flow](assets/LSC-CICD-Flow.png)

[Open the editable SVG](assets/LSC-CICD-Flow.svg)

## Which workflows are represented?

| Area | Workflow file | What the diagram represents |
|---|---|---|
| Android | `MJjimmy/LSC-APP/.github/workflows/android.yml` | Checkout/tool verification, JVM tests, debug APK assembly and artifact publication. |
| Backend integration | `.github/workflows/final-integration.yml` on `deploy/railpack` | Combined administrator and authentication regression checks. |
| PHP/provider review | `.github/workflows/railpack-review.yml` on `deploy/railpack` | Offline provider/controller and PHP checks. The recorded run's runtime/database job was skipped. |
| Security/cache | `.github/workflows/stage2-security.yml` on `deploy/railpack` | Security, cache and browser regressions when that workflow's conditions match. |

Workflow conditions differ. This does **not** mean every registered workflow runs on every push, all historical workflows are passing, or all workflows tested the latest deployment revision. The separate historical `main` CI is not depicted as the current production branch's pipeline.

## Verified example runs

- [Android build and tests — run 40415126298](https://github.com/MJjimmy/LSC-APP/actions/runs/40415126298): source `1a46282` on `master`; the actual downloaded report showed **42 tests, 0 failures/errors/skips**. This is not the newer local 63-test build.
- [Combined backend checks — run 40624555112](https://github.com/MJjimmy/LSC/actions/runs/40624555112): source `2241e48` on `deploy/railpack`.
- [Railpack review — run 40624555069](https://github.com/MJjimmy/LSC/actions/runs/40624555069): source `2241e48`; inspect the skipped runtime/database job rather than treating it as passed.
- [Stage 2 security/cache checks — run 40623329843](https://github.com/MJjimmy/LSC/actions/runs/40623329843): source `03eb2a6`, an earlier revision than `2241e48`.

The diagram contains no credentials. It does not represent an automatic Play Store release, a real-provider authentication test, or proof of authenticated production/database acceptance.
